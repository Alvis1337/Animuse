<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\OAuthState;
use App\Models\MalToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;

class MalOauth extends Controller
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $authorizationEndpoint = 'https://myanimelist.net/v1/oauth2/authorize';
    private $tokenEndpoint = 'https://myanimelist.net/v1/oauth2/token';

    public function __construct()
    {
        $this->clientId = env('MAL_CLIENT_ID');
        $this->clientSecret = env('MAL_CLIENT_SECRET');
        $this->redirectUri = env('MAL_REDIRECT_URI');
    }

    private function generateCodeVerifier($length = 64)
    {
        return Str::random($length);
    }

    private function generateCodeChallenge($codeVerifier)
    {
        return $codeVerifier;
    }

    public function init(Request $request)
    {
        $username = $request->input('username');
        if (empty($username)) {
            return redirect()->route('mal.oauth.show')->with('error', 'Please enter a username.');
        }
    
        try {
            $existingCodeRecord = DB::table('o_auth_states')->where('username', $username)->first();
            $codeVerifier = $existingCodeRecord ? $existingCodeRecord->code_verifier : $this->generateCodeVerifier();
    
            if (!$existingCodeRecord) {
                DB::table('o_auth_states')->insert([
                    'username' => $username,
                    'code_verifier' => $codeVerifier,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
    
            $codeChallenge = $this->generateCodeChallenge($codeVerifier);
    
            $authorizationUrl = $this->authorizationEndpoint . '?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'plain',
            ]);

            Log::info('Init method called with username: ' . $username);
    
            return redirect()->route('mal.oauth.show')->with('authorizationUrl', $authorizationUrl);
    
        } catch (\Exception $e) {
            Log::error('Error during OAuth initiation: ' . $e->getMessage());
            return redirect()->route('mal.oauth.show')->with('error', 'An error occurred during OAuth initiation.');
        }
    }
    

    public function handleOauthCallback(Request $request)
    {
        if (!$this->clientId || !$this->clientSecret || !$this->redirectUri) {
            return response()->json(['message' => 'Missing environment variables'], 500);
        }

        try {
            $authorizationCode = $request->query('code');

            if (!$authorizationCode) {
                return response()->json(['message' => 'Missing authorization code'], 400);
            }

            $codeRecord = OAuthState::orderBy('created_at', 'desc')->first();
            if (!$codeRecord) {
                return response()->json(['message' => 'Code verifier not found'], 400);
            }

            $tokenResponse = Http::asForm()->post($this->tokenEndpoint, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $authorizationCode,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeRecord->code_verifier,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Token request failed', ['response' => $tokenResponse->json()]);
                return response()->json(['message' => 'Failed to obtain token'], 500);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;

            if (!$accessToken || !$refreshToken) {
                return response()->json(['message' => 'Missing access or refresh token'], 400);
            }

            $userResponse = Http::withToken($accessToken)->get('https://api.myanimelist.net/v2/users/@me');
            if ($userResponse->failed()) {
                throw new \Exception('Failed to fetch user data');
            }

            $userData = $userResponse->json();
            $username = $userData['name'];

            MalToken::updateOrCreate(
                ['username' => $username],
                ['token' => $accessToken, 'refreshToken' => $refreshToken]
            );

            $cookie = Cookie::make('authToken', $accessToken, $tokenData['expires_in'] / 60, '/', null, true, true);

            return redirect('/')->withCookie($cookie);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'An error occurred during OAuth'], 500);
        }
    }

    public function step1(Request $request)
    {
        $username = $request->query('username');

        if (!$username) {
            return response()->json(['message' => 'Username is required'], 400);
        }

        try {
            $existingCodeRecord = DB::table('o_auth_states')->where('username', $username)->first();

            $codeVerifier = '';
            if ($existingCodeRecord) {
                $codeVerifier = $existingCodeRecord->code_verifier;
            } else {
                $codeVerifier = $this->generateCodeVerifier();
                DB::table('o_auth_states')->insert([
                    'username' => $username,
                    'code_verifier' => $codeVerifier,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $codeChallenge = $this->generateCodeChallenge($codeVerifier);

            if ($this->clientId && $this->redirectUri) {
                $authorizationUrl = $this->authorizationEndpoint . '?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => $this->clientId,
                    'redirect_uri' => $this->redirectUri,
                    'code_challenge' => $codeChallenge,
                    'code_challenge_method' => 'plain'
                ]);
                return response()->json(['authorizationUrl' => $authorizationUrl]);
            } else {
                return response()->json(['message' => 'No client ID or redirect URI specified'], 400);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            Log::error('Error during OAuth step 1: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred.'], 500);
        }
    }

    // Show user information
    public function show()
    {
        $user = MalToken::where('username', session('username'))->first();

        if (!$user) {
            return redirect()->route('mal.oauth.init')->with('error', 'No user information found.');
        }

        return view('mal_oauth', ['user' => $user]);
    }
}
