<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - CAS</title>
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
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header h1 {
            color: #333;
            margin: 0;
            font-size: 1.8rem;
        }
        .register-header p {
            color: #666;
            margin: 0.5rem 0 0 0;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #fcc;
        }
        .success {
            background: #efe;
            color: #363;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #cfc;
        }
        .links {
            text-align: center;
            margin-top: 1rem;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        .password-requirements {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #666;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 1.2rem;
        }
        .password-requirements li {
            margin-bottom: 0.2rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>用户注册</h1>
            <p>创建您的CAS账户</p>
        </div>

        @if($errors && $errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if(session('error'))
            <div class="error">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="success">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('cas.post.register') }}">
            @csrf
            
            @if($service)
                <input type="hidden" name="service" value="{{ $service }}">
            @endif
            
            @if($from)
                <input type="hidden" name="from" value="{{ $from }}">
            @endif

            <div class="form-group">
                <label for="name">姓名</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">邮箱地址</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
                <div id="password-strength" class="password-strength"></div>
                <div class="password-requirements">
                    <strong>密码要求：</strong>
                    <ul>
                        <li>至少8个字符</li>
                        <li>包含大写字母</li>
                        <li>包含小写字母</li>
                        <li>包含数字</li>
                        <li>包含特殊字符（推荐）</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">确认密码</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">注册账户</button>
            </div>
        </form>

        <div class="links">
            <a href="{{ route('cas.login') }}{{ $service ? '?service=' . urlencode($service) : '' }}">已有账户？立即登录</a>
        </div>
    </div>

    <script>
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('至少8个字符');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('包含大写字母');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('包含小写字母');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('包含数字');
            
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('包含特殊字符');
            
            if (strength < 3) {
                strengthDiv.className = 'password-strength strength-weak';
                strengthDiv.textContent = '密码强度：弱 - 还需要：' + feedback.join('、');
            } else if (strength < 5) {
                strengthDiv.className = 'password-strength strength-medium';
                strengthDiv.textContent = '密码强度：中等 - 建议添加：' + feedback.join('、');
            } else {
                strengthDiv.className = 'password-strength strength-strong';
                strengthDiv.textContent = '密码强度：强';
            }
        });
    </script>
</body>
</html>