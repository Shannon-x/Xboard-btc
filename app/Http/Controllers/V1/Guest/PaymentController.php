<?php

namespace App\Http\Controllers\V1\Guest;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function notify($method, $uuid, Request $request)
    {
        \Log::info('Payment notification received', [
            'method' => $method,
            'uuid' => $uuid,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all()
        ]);

        try {
            $paymentService = new PaymentService($method, null, $uuid);
            $verify = $paymentService->notify($request->input());
            
            if (!$verify) {
                \Log::warning('Payment verification failed', [
                    'method' => $method,
                    'uuid' => $uuid,
                    'request_data' => $request->all()
                ]);
                return $this->fail([422, 'verify error']);
            }
            
            if (!$this->handle($verify['trade_no'], $verify['callback_no'])) {
                \Log::error('Payment handle failed', [
                    'method' => $method,
                    'uuid' => $uuid,
                    'trade_no' => $verify['trade_no'],
                    'callback_no' => $verify['callback_no']
                ]);
                return $this->fail([400, 'handle error']);
            }
            
            \Log::info('Payment notification processed successfully', [
                'method' => $method,
                'trade_no' => $verify['trade_no'],
                'callback_no' => $verify['callback_no']
            ]);
            
            return (isset($verify['custom_result']) ? $verify['custom_result'] : 'success');
        } catch (\Exception $e) {
            \Log::error('Payment notification error', [
                'method' => $method,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return $this->fail([500, 'fail']);
        }
    }

    private function handle($tradeNo, $callbackNo)
    {
        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) {
            return $this->fail([400202, 'order is not found']);
        }
        if ($order->status !== Order::STATUS_PENDING)
            return true;
        $orderService = new OrderService($order);
        if (!$orderService->paid($callbackNo)) {
            return false;
        }

        $payment = Payment::where('id', $order->payment_id)->first();
        $telegramService = new TelegramService();
        $message = sprintf(
            "💰成功收款%s元\n" .
            "———————————————\n" .
            "支付接口：%s\n" .
            "支付渠道：%s\n" .
            "本站订单：`%s`"
            ,
            $order->total_amount / 100,
            $payment->payment,
            $payment->name,
            $order->trade_no
        );
        
        $telegramService->sendMessageWithAdmin($message);
        return true;
    }
}
