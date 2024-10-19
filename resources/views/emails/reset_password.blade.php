<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
</head>
<body>
    <p><img src="{{ asset('berkompeten_logo.png') }}" alt="Logo"></p>
    <br>
    <p>Hello,</p>

    <p>Click on the following link to reset your password:</p>
    <a href="{{ $emailData['reset_link'] }}">{{ $emailData['reset_link'] }}</a>

    <p>If you did not request a password reset, please ignore this email.</p>

    <p>Thank you,</p>
</body>
</html>
