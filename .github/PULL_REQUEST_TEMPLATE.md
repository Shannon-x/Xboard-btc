# BTCPay Server 支付集成优化

## 📋 变更概述

本PR对Xboard中的BTCPay Server支付集成进行了全面优化，修复了现有问题并添加了新功能。

## 🔧 主要改进

### 1. API升级
- ✅ **升级到Greenfield API**: 弃用旧的Legacy API，使用BTCPay Server最新API标准
- ✅ **API密钥格式更新**: 支持新的64位Greenfield API密钥（不再使用`token_`前缀）
- ✅ **权限管理优化**: 明确指定所需的API权限

### 2. 安全增强
- ✅ **Webhook签名验证**: 完善HMAC签名验证机制
- ✅ **错误处理优化**: 详细的错误信息和日志记录
- ✅ **SSL/TLS验证**: 强制HTTPS连接验证

### 3. 功能改进
- ✅ **支付流程优化**: 改进支付参数和发票配置
- ✅ **通知处理增强**: 更可靠的支付通知处理
- ✅ **手续费支持**: 支持固定和百分比手续费配置
- ✅ **多币种支持**: 支持BTCPay Server的所有可用加密货币

### 4. 开发工具
- ✅ **配置测试命令**: 新增`php artisan btcpay:test`测试工具
- ✅ **自动化脚本**: 提供一键配置和测试脚本
- ✅ **详细文档**: 完整的配置指南和使用说明

## 📁 文件变更

### 新增文件
- `app/Console/Commands/TestBTCPay.php` - BTCPay配置测试命令
- `docs/BTCPay_Configuration_Guide.md` - 完整配置指南
- `docs/BTCPay_Frontend_Configuration.md` - 前端配置说明
- `docs/BTCPay_Admin_Interface.md` - 管理界面说明
- `btcpay_setup.sh` - 自动化配置脚本
- `BTCPay_README.md` - 优化总览文档

### 修改文件
- `app/Payments/BTCPay.php` - 核心支付逻辑重构
- `app/Console/Kernel.php` - 注册新测试命令
- `app/Http/Controllers/V1/Guest/PaymentController.php` - 增强日志记录

## 🧪 测试

### 测试命令
```bash
# 检查配置
php artisan btcpay:test --payment-id=1 --check-config

# 创建测试发票
php artisan btcpay:test --payment-id=1 --test-invoice

# 运行配置脚本
bash btcpay_setup.sh
```

### 测试场景
- [x] API连接测试
- [x] 发票创建测试
- [x] Webhook通知测试
- [x] 错误处理测试
- [x] 安全验证测试

## 🔄 向后兼容性

- ✅ **完全向后兼容**: 现有配置无需修改即可使用
- ✅ **平滑升级**: 支持从Legacy API逐步迁移到Greenfield API
- ✅ **配置保持**: 所有现有配置参数保持不变

## 📚 配置指南

### BTCPay Server端配置
1. 创建Greenfield API密钥（权限：`btcpay.store.cancreateinvoice`, `btcpay.store.canviewinvoices`）
2. 配置Webhook（URL：`/api/v1/guest/payment/notify/BTCPay/{UUID}`）
3. 获取Store ID

### Xboard端配置
1. 在管理后台添加BTCPay支付方式
2. 填写API地址、Store ID、API密钥等参数
3. 配置Webhook Secret
4. 设置手续费（可选）

详细配置步骤请参考：`docs/BTCPay_Configuration_Guide.md`

## ⚠️ 注意事项

1. **API密钥格式**: 新版本使用64位Greenfield API密钥，不再使用`token_`前缀
2. **权限要求**: 确保API密钥具有创建和查看发票的权限
3. **Webhook配置**: 必须在BTCPay Server中正确配置Webhook才能接收支付通知
4. **HTTPS要求**: 生产环境必须使用HTTPS

## 🐛 修复的问题

- 修复API密钥格式错误
- 修复Webhook签名验证问题
- 修复错误处理不完善的问题
- 修复日志记录缺失的问题
- 修复支付通知处理异常

## 📖 相关文档

- [BTCPay Server官方文档](https://docs.btcpayserver.org/)
- [Greenfield API文档](https://docs.btcpayserver.org/API/Greenfield/v1/)
- [配置指南](docs/BTCPay_Configuration_Guide.md)

## 🎯 影响范围

- **影响模块**: 支付系统
- **风险等级**: 低（向后兼容）
- **部署要求**: 无特殊要求
- **数据迁移**: 不需要

## ✅ 检查清单

- [x] 代码审查完成
- [x] 功能测试通过
- [x] 文档更新完成
- [x] 向后兼容性验证
- [x] 安全审查通过
