<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP</title>
</head>
<body>
    <p><img src="{{ asset('berkompeten_logo.png') }}" alt="Logo"></p>
    <br>
    <p>Hello,</p>

    <p>Your One-Time Password (OTP) for resetting your password is: <strong>{{ $emailData['otp'] }}</strong></p>

    <p>Click on the following link to reset your password:</p>
    <a href="{{ $emailData['reset_link'] }}">{{ $emailData['reset_link'] }}</a>

    <p>This OTP is valid for the next 1 minute.</p>

    <p>If you did not request a password reset, please ignore this email.</p>

    <p>Thank you,</p>
</body>
</html>
