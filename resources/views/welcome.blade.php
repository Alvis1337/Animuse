<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyAnimeList OAuth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1>MyAnimeList OAuth</h1>

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if(isset($user))
            <div class="card">
                <div class="card-header">
                    <h4>Welcome back, {{ $user->username }}!</h4>
                </div>
                <div class="card-body">
                    <p><strong>Access Token:</strong> {{ $user->token }}</p>
                    <p><strong>Refresh Token:</strong> {{ $user->refreshToken }}</p>
                    <p><strong>Last Updated:</strong> {{ $user->updated_at }}</p>
                </div>
            </div>
        @else
            <form action="{{ route('mal.oauth.init') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="username" class="form-label">Enter Your MyAnimeList Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Start OAuth Process</button>
            </form>
        @endif
    </div>
</body>

</html>