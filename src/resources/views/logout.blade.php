<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAS 登出</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logout-header {
            margin-bottom: 2rem;
        }
        .logout-header h1 {
            color: #333;
            margin: 0;
            font-size: 1.8rem;
        }
        .logout-header p {
            color: #666;
            margin: 0.5rem 0 0 0;
        }
        .success-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .message {
            color: #333;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .service-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .service-info h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        .service-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-header">
            <div class="success-icon">✓</div>
            <h1>登出成功</h1>
            <p>您已安全退出系统</p>
        </div>

        <div class="message">
            <p>您已成功从 CAS 统一身份认证系统登出。</p>
            @if(request('service'))
                <div class="service-info">
                    <h3>服务信息</h3>
                    <p>您将被重定向到：{{ request('service') }}</p>
                </div>
            @endif
        </div>

        @if(request('service'))
            <a href="{{ request('service') }}" class="btn">返回应用</a>
        @else
            <a href="{{ route('cas.login') }}" class="btn">重新登录</a>
        @endif
    </div>

    @if(request('service'))
        <script>
            // 3秒后自动跳转
            setTimeout(function() {
                window.location.href = '{{ request('service') }}';
            }, 3000);
        </script>
    @endif
</body>
</html>