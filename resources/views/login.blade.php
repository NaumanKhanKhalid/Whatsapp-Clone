<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WhatsApp Theme</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/whatsapp.png') }}">
</head>
<style>
    /* General Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Roboto', sans-serif;
}

/* Body Background */
body {
    background-color: #ece5dd;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

/* Login Container */
.login-container {
    background-color: #ffffff;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    width: 400px;
    padding: 20px;
    text-align: center;
}

/* Header */
.login-header {
    margin-bottom: 20px;
}

.login-logo {
    width: 60px;
    margin-bottom: 10px;
}

.login-header h2 {
    font-size: 24px;
    color: #128c7e;
    margin-bottom: 5px;
}

.login-header p {
    font-size: 14px;
    color: #555;
}

/* Form */
.login-form .form-group {
    margin-bottom: 15px;
    text-align: left;
}

.login-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.login-form input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.login-form input:focus {
    outline: none;
    border-color: #128c7e;
}

/* Button */
.btn-login {
    width: 100%;
    background-color: #25d366;
    color: #fff;
    padding: 10px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-login:hover {
    background-color: #128c7e;
}

</style>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="{{ asset('images/whatsapp-logo.png') }}" alt="WhatsApp Logo" class="login-logo">
                <h2>Welcome Back</h2>
                <p>Log in to continue chatting</p>
            </div>
            <form action="{{ route('login') }}" method="POST" enctype="multipart/form-data" class="login-form">
                @csrf
                <div class="form-group">
                    <label for="email">email</label>
                    <input type="text" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="password">password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                {{-- <div class="form-group">
                    <label for="image">Profile Picture</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div> --}}
                <button type="submit" class="btn-login">Log In</button>
            </form>
        </div>
    </div>
</body>

</html>
