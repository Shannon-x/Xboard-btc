<?php

namespace App\Payments;

use App\Exceptions\ApiException;
use App\Contracts\PaymentInterface;

class BTCPay implements PaymentInterface
{
    protected $config;
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form(): array
    {
        return [
            'btcpay_url' => [
                'label' => 'API接口所在网址(包含最后的斜杠)',
                'description' => '您的BTCPay Server地址，例如：https://btcpay.example.com/',
                'type' => 'input',
                'placeholder' => 'https://btcpay.example.com/',
            ],
            'btcpay_storeId' => [
                'label' => 'Store ID',
                'description' => 'BTCPay Server商店ID，在商店设置中可以找到',
                'type' => 'input',
                'placeholder' => '商店设置 -> 一般设置 -> Store ID',
            ],
            'btcpay_api_key' => [
                'label' => 'Greenfield API 密钥',
                'description' => '在账户设置 -> 管理访问令牌中创建Greenfield API密钥，需要权限：btcpay.store.cancreateinvoice, btcpay.store.canviewinvoices',
                'type' => 'input',
                'placeholder' => 'Greenfield API密钥（64位字符串）',
            ],
            'btcpay_webhook_key' => [
                'label' => 'WEBHOOK SECRET',
                'description' => '在商店设置 -> Webhooks中配置的密钥，用于验证通知的真实性',
                'type' => 'input',
                'placeholder' => '随机生成的密钥字符串',
            ],
            'handling_fee_fixed' => [
                'label' => '固定手续费(分)',
                'description' => '每笔交易的固定手续费，单位为分(1元=100分)',
                'type' => 'input',
                'placeholder' => '0',
            ],
            'handling_fee_percent' => [
                'label' => '百分比手续费(%)',
                'description' => '按交易金额收取的百分比手续费，0-100之间的数值',
                'type' => 'input',
                'placeholder' => '0',
            ],
        ];
    }

    public function pay($order): array
    {
        // 验证必要的配置参数
        if (empty($this->config['btcpay_url']) || empty($this->config['btcpay_storeId']) || empty($this->config['btcpay_api_key'])) {
            throw new ApiException('BTCPay配置不完整，请检查API接口地址、Store ID和API KEY');
        }

        // 创建发票参数
        $params = [
            'amount' => sprintf('%.2f', $order['total_amount'] / 100),
            'currency' => 'CNY', // 根据需要可以改为USD或其他货币
            'metadata' => [
                'orderId' => $order['trade_no'],
                'userId' => $order['user_id'] ?? null,
                'itemDesc' => '订单支付',
                'buyerEmail' => $order['email'] ?? null,
            ],
            'checkout' => [
                'redirectURL' => $order['return_url'] ?? url('/#/order/' . $order['trade_no']),
                'speedPolicy' => 'MediumSpeed', // 交易确认速度策略
            ],
            'receipt' => [
                'enabled' => true,
                'showQR' => true,
            ],
            'notificationURL' => $order['notify_url'] ?? null,
        ];

        $params_string = json_encode($params);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('创建支付参数时出错：' . json_last_error_msg());
        }

        // 发送请求到BTCPay Server
        $url = rtrim($this->config['btcpay_url'], '/') . '/api/v1/stores/' . $this->config['btcpay_storeId'] . '/invoices';
        $ret_raw = $this->_curlPost($url, $params_string);

        if ($ret_raw === false) {
            throw new ApiException('BTCPay Server连接失败，请检查API地址配置');
        }

        $ret = json_decode($ret_raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('BTCPay Server返回数据格式错误');
        }

        if (isset($ret['error'])) {
            throw new ApiException('BTCPay Server错误：' . ($ret['error']['message'] ?? $ret_raw));
        }

        if (empty($ret['checkoutLink'])) {
            \Log::error('BTCPay创建订单失败', [
                'order_id' => $order['trade_no'],
                'response' => $ret_raw,
                'config' => [
                    'url' => $this->config['btcpay_url'],
                    'store_id' => $this->config['btcpay_storeId']
                ]
            ]);
            throw new ApiException('创建支付订单失败，请联系客服');
        }

        return [
            'type' => 1, // 重定向到支付页面
            'data' => $ret['checkoutLink'],
        ];
    }

    public function notify($params): array|bool
    {
        $payload = trim(request()->getContent());
        
        if (empty($payload)) {
            \Log::warning('BTCPay通知：空的请求体');
            throw new ApiException('Empty payload', 400);
        }

        $headers = getallheaders();
        
        // BTCPay Server使用BTCPay-Sig头进行签名验证
        $signatureHeader = null;
        foreach (['BTCPay-Sig', 'Btcpay-Sig', 'btcpay-sig'] as $headerName) {
            if (isset($headers[$headerName])) {
                $signatureHeader = $headers[$headerName];
                break;
            }
        }

        if (empty($signatureHeader)) {
            \Log::warning('BTCPay通知：缺少签名头');
            throw new ApiException('Missing signature header', 400);
        }

        // 验证Webhook签名（如果配置了webhook密钥）
        if (!empty($this->config['btcpay_webhook_key'])) {
            $computedSignature = "sha256=" . hash_hmac('sha256', $payload, $this->config['btcpay_webhook_key']);
            
            if (!$this->hashEqual($signatureHeader, $computedSignature)) {
                \Log::warning('BTCPay通知：签名验证失败', [
                    'expected' => $computedSignature,
                    'received' => $signatureHeader
                ]);
                throw new ApiException('HMAC signature does not match', 400);
            }
        }

        $json_param = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('BTCPay通知：JSON解析失败', ['error' => json_last_error_msg()]);
            throw new ApiException('Invalid JSON payload', 400);
        }

        if (empty($json_param['invoiceId'])) {
            \Log::warning('BTCPay通知：缺少invoiceId');
            throw new ApiException('Missing invoiceId', 400);
        }

        // 验证通知类型，只处理已支付的发票
        if (isset($json_param['type']) && !in_array($json_param['type'], ['InvoicePaymentSettled', 'InvoiceProcessing', 'InvoiceExpired'])) {
            \Log::info('BTCPay通知：忽略通知类型', ['type' => $json_param['type']]);
            return false;
        }

        // 从BTCPay Server获取发票详情
        try {
            $invoiceDetail = $this->getInvoiceDetail($json_param['invoiceId']);
        } catch (\Exception $e) {
            \Log::error('BTCPay通知：获取发票详情失败', ['error' => $e->getMessage()]);
            throw new ApiException('Failed to get invoice details: ' . $e->getMessage(), 500);
        }

        if (empty($invoiceDetail['metadata']['orderId'])) {
            \Log::warning('BTCPay通知：发票中缺少订单ID', ['invoice_id' => $json_param['invoiceId']]);
            throw new ApiException('Missing orderId in invoice metadata', 400);
        }

        // 检查支付状态
        $status = $invoiceDetail['status'] ?? '';
        if (!in_array($status, ['Settled', 'Processing', 'Confirmed'])) {
            \Log::info('BTCPay通知：发票状态不符合支付条件', [
                'invoice_id' => $json_param['invoiceId'],
                'status' => $status
            ]);
            return false;
        }

        $out_trade_no = $invoiceDetail['metadata']['orderId'];
        $pay_trade_no = $json_param['invoiceId'];

        \Log::info('BTCPay通知：支付成功', [
            'order_id' => $out_trade_no,
            'invoice_id' => $pay_trade_no,
            'status' => $status
        ]);

        return [
            'trade_no' => $out_trade_no,
            'callback_no' => $pay_trade_no
        ];
    }

    /**
     * 获取发票详情
     */
    private function getInvoiceDetail($invoiceId)
    {
        $url = rtrim($this->config['btcpay_url'], '/') . '/api/v1/stores/' . $this->config['btcpay_storeId'] . '/invoices/' . $invoiceId;
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    "Authorization: token " . $this->config['btcpay_api_key'],
                    "Content-Type: application/json"
                ],
                'timeout' => 30
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception('Failed to connect to BTCPay Server');
        }

        $invoiceDetail = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from BTCPay Server');
        }

        if (isset($invoiceDetail['error'])) {
            throw new \Exception('BTCPay Server error: ' . ($invoiceDetail['error']['message'] ?? 'Unknown error'));
        }

        return $invoiceDetail;
    }


    private function _curlPost($url, $params = false)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->config['btcpay_api_key'],
                'Content-Type: application/json',
                'User-Agent: Xboard-BTCPay/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($result === false) {
            \Log::error('BTCPay cURL错误', ['error' => $error, 'url' => $url]);
            throw new ApiException('网络连接失败：' . $error);
        }
        
        if ($httpCode >= 400) {
            \Log::error('BTCPay HTTP错误', [
                'http_code' => $httpCode,
                'response' => $result,
                'url' => $url
            ]);
            
            if ($httpCode === 401) {
                throw new ApiException('API密钥无效或已过期');
            } elseif ($httpCode === 404) {
                throw new ApiException('商店ID不存在或API端点不正确');
            } else {
                throw new ApiException('BTCPay Server错误 (HTTP ' . $httpCode . ')');
            }
        }
        
        return $result;
    }


    /**
     * @param string $str1
     * @param string $str2
     * @return bool
     */
    private function hashEqual($str1, $str2)
    {

        if (function_exists('hash_equals')) {
            return \hash_equals($str1, $str2);
        }

        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;

            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        }
    }
}
