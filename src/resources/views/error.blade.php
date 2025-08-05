<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAS 错误</title>
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
        .error-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .error-header {
            margin-bottom: 2rem;
        }
        .error-header h1 {
            color: #dc3545;
            margin: 0;
            font-size: 1.8rem;
        }
        .error-header p {
            color: #666;
            margin: 0.5rem 0 0 0;
        }
        .error-icon {
            font-size: 3rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .error-message {
            background: #fee;
            color: #c33;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
            border: 1px solid #fcc;
            text-align: left;
        }
        .error-code {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .error-description {
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
            margin: 0 0.5rem;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .actions {
            margin-top: 1rem;
        }
        .debug-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            text-align: left;
            font-size: 0.9rem;
            color: #666;
        }
        .debug-info h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <div class="error-icon">⚠</div>
            <h1>认证错误</h1>
            <p>CAS 统一身份认证系统</p>
        </div>

        <div class="error-message">
            <div class="error-code">
                错误代码: {{ $errorCode ?? 'UNKNOWN_ERROR' }}
            </div>
            <div class="error-description">
                {{ $errorMessage ?? '发生了未知错误，请稍后重试。' }}
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('cas.login') }}" class="btn">重新登录</a>
            @if(request('service'))
                <a href="{{ request('service') }}" class="btn btn-secondary">返回应用</a>
            @endif
        </div>

        @if(config('app.debug') && isset($debugInfo))
            <div class="debug-info">
                <h4>调试信息</h4>
                <pre>{{ $debugInfo }}</pre>
            </div>
        @endif
    </div>
</body>
</html>