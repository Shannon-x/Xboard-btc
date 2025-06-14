# BTCPay Server 优化部署检查清单

## 📋 部署前检查

### 系统要求
- [ ] PHP 7.4+ 
- [ ] Laravel 8.0+
- [ ] BTCPay Server v1.6.0+
- [ ] HTTPS 配置（生产环境必需）

### 文件检查
- [ ] `app/Payments/BTCPay.php` 已更新
- [ ] `app/Console/Commands/TestBTCPay.php` 已添加
- [ ] `app/Console/Kernel.php` 已更新
- [ ] 文档文件已添加到 `docs/` 目录
- [ ] 配置脚本 `btcpay_setup.sh` 已添加

## 🔧 配置步骤

### 1. BTCPay Server 配置
- [ ] 创建 Greenfield API 密钥
  - [ ] 权限：`btcpay.store.cancreateinvoice`
  - [ ] 权限：`btcpay.store.canviewinvoices`
- [ ] 记录 Store ID
- [ ] 配置 Webhook
  - [ ] URL：`https://your-domain.com/api/v1/guest/payment/notify/BTCPay/{UUID}`
  - [ ] 事件：Invoice Settled, Invoice Processing
  - [ ] 生成并保存 Secret

### 2. Xboard 配置
- [ ] 清除缓存：`php artisan config:clear`
- [ ] 在管理后台添加 BTCPay 支付方式
- [ ] 配置参数：
  - [ ] API 接口地址（以 `/` 结尾）
  - [ ] Store ID
  - [ ] Greenfield API 密钥
  - [ ] Webhook Secret
  - [ ] 手续费设置（可选）

### 3. 测试验证
- [ ] 运行配置测试：`php artisan btcpay:test --payment-id=X --check-config`
- [ ] 创建测试发票：`php artisan btcpay:test --payment-id=X --test-invoice`
- [ ] 前台支付流程测试
- [ ] 支付通知回调测试

## 🔍 验证检查

### API 连接测试
```bash
# 运行连通性测试
php artisan btcpay:test --payment-id=1 --check-config
```

预期结果：
- [ ] ✓ 基础配置完整
- [ ] ✓ API连接成功
- [ ] ✓ 商店信息获取成功

### 支付流程测试
```bash
# 创建测试发票
php artisan btcpay:test --payment-id=1 --test-invoice
```

预期结果：
- [ ] ✓ 测试发票创建成功
- [ ] 返回有效的支付链接
- [ ] 支付页面正常显示

### 前台测试
- [ ] 支付方式在前台正常显示
- [ ] 点击支付能跳转到 BTCPay 页面
- [ ] 支付金额和信息显示正确
- [ ] 支付完成后能正确返回

### 通知测试
- [ ] 支付成功后订单状态正确更新
- [ ] 日志中有支付通知记录
- [ ] 没有重复处理问题

## 📊 监控设置

### 日志监控
```bash
# 实时查看 BTCPay 日志
tail -f storage/logs/laravel.log | grep BTCPay

# 查看支付通知日志
grep "Payment notification" storage/logs/laravel.log
```

### 关键指标
- [ ] 支付成功率
- [ ] API 响应时间
- [ ] 错误率统计
- [ ] 通知到达率

## 🚨 故障排除

### 常见问题检查

**API 连接失败**
- [ ] 检查 BTCPay Server 地址是否正确
- [ ] 验证网络连接和防火墙
- [ ] 确认 SSL 证书有效
- [ ] 检查 API 密钥格式（64位字符串）

**权限错误**
- [ ] 验证 API 密钥权限
- [ ] 检查 Store ID 是否正确
- [ ] 确认 API 密钥未过期

**Webhook 通知失败**
- [ ] 检查 Webhook URL 是否可访问
- [ ] 验证 Secret 配置是否一致
- [ ] 查看服务器访问日志
- [ ] 确认防火墙允许 BTCPay Server IP

**支付状态异常**
- [ ] 检查订单状态逻辑
- [ ] 验证支付通知处理
- [ ] 查看详细错误日志

## 🔒 安全检查

### API 安全
- [ ] API 密钥使用最小权限
- [ ] 避免在日志中记录敏感信息
- [ ] 定期轮换 API 密钥

### Webhook 安全
- [ ] 使用强随机 Secret（32位以上）
- [ ] 启用签名验证
- [ ] 仅接受来自 BTCPay Server 的请求

### 服务器安全
- [ ] 配置适当的防火墙规则
- [ ] 启用 HTTPS
- [ ] 监控异常访问行为

## 📈 性能优化

### 缓存配置
- [ ] 缓存 BTCPay Server 状态
- [ ] 优化 API 请求频率
- [ ] 配置适当的超时时间

### 数据库优化
- [ ] 支付相关表索引优化
- [ ] 定期清理过期订单
- [ ] 监控数据库性能

## 📝 文档更新

- [ ] 更新部署文档
- [ ] 记录配置参数
- [ ] 更新用户使用指南
- [ ] 维护故障排除手册

## ✅ 部署完成确认

### 功能验证
- [ ] 所有测试用例通过
- [ ] 支付流程完整可用
- [ ] 通知机制正常工作
- [ ] 错误处理符合预期

### 监控就绪
- [ ] 日志监控配置完成
- [ ] 告警规则设置完成
- [ ] 性能指标收集就绪

### 文档齐全
- [ ] 配置文档已更新
- [ ] 操作手册已准备
- [ ] 故障排除指南已完善

---

**部署完成时间**: ___________  
**部署人员**: ___________  
**验证人员**: ___________  

## 🔄 回滚方案

如遇问题，可以通过以下方式回滚：

1. **代码回滚**
   ```bash
   git revert <commit-hash>
   git push origin master
   ```

2. **配置回滚**
   - 恢复原 BTCPay 配置
   - 重启相关服务

3. **数据库回滚**
   - 如有数据库变更，恢复备份
   - 验证数据一致性
