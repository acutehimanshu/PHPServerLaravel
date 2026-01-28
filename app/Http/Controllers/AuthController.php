<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuthLog;
use App\Models\UserProfile;
use App\Services\EmailService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation error', 422, $validator->errors());
            }

            $user = User::create([
                'name'     => trim($request->name),
                'email'    => strtolower($request->email),
                'password' => Hash::make($request->password),
                'status'   => 'active',
            ]);

            // Optional profile
            UserProfile::create([
                'user_id'      => $user->id,
                'phone'        => data_get($request, 'profile.phone'),
                'country_code' => data_get($request, 'profile.country_code'),
                'language'     => data_get($request, 'profile.language', 'en'),
                'timezone'     => data_get($request, 'profile.timezone'),
                'metadata'     => data_get($request, 'profile.metadata'),
                'device_info'  => is_array($request->device) ? $request->device : null,
            ]);

            AuthLog::create([
                'user_id'    => $user->id,
                'email'      => $user->email,
                'action'     => 'register',
                'status'     => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            // JWT generation AFTER commit
            $token = auth()->login($user);

            if (config('services.email.enabled', false)) {
                EmailService::send($user, 'Welcome!', 'Your account has been created.');
            }

            if (config('services.sms.enabled', false)) {
                SmsService::send($user, 'Welcome to our platform!');
            }

            return $this->respondWithToken($token, 'Registration successful');

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return $this->error('Registration failed', 500);
        }
    }

    /**
     * Login user and generate JWT token
     */
    public function login(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'email'    => 'required|email',
                'password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation error', 422, $validator->errors());
            }

            if (!$token = auth()->attempt($request->only('email', 'password'))) {
                AuthLog::create([
                    'email'      => $request->email,
                    'action'     => 'login',
                    'status'     => 'failed',
                    'ip_address' => $request->ip(),
                    'message'    => 'Invalid credentials',
                ]);

                DB::commit();

                return $this->error('Invalid email or password', 401);
            }

            $user = auth()->user();

            if ($user->status !== 'active') {
                DB::commit();
                return $this->error('Account is not active', 403);
            }

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            AuthLog::create([
                'user_id'    => $user->id,
                'email'      => $user->email,
                'action'     => 'login',
                'status'     => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_info'=> is_array($request->device) ? $request->device : null,
            ]);

            DB::commit();

            if (config('services.email.enabled', false)) {
                EmailService::send($user, 'Login Alert', 'You logged in successfully.');
            }

            if (config('services.sms.enabled', false)) {
                SmsService::send($user, 'Login successful.');
            }

            return $this->respondWithToken($token, 'Login successful');

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return $this->error('Login failed', 500);
        }
    }

    /**
     * Get authenticated user
     */
     public function me()
    {
        try {
            return $this->success(auth()->user(), 'User profile retrieved');
        } catch (TokenExpiredException $exception) {
            return $this->error('Token has expired', 401);
        } catch (TokenInvalidException $exception) {
            return $this->error('Token is invalid', 401);
        } catch (JWTException $exception) {
            return $this->error('Token not found', 401);
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        try {
            AuthLog::create([
                'user_id' => auth()->id(),
                'action'  => 'logout',
                'status'  => 'success',
            ]);

            auth()->logout();

            return $this->success(null, 'Successfully logged out');
        } catch (JWTException $e) {
            report($e);
            return $this->error('Logout failed', 401);
        }
    }

    /**
     * Refresh JWT token
     */
    public function refresh()
    {
        try {
            $token = auth()->refresh();
            return $this->respondWithToken($token, 'Token refreshed successfully');
        } catch (TokenExpiredException | TokenInvalidException | JWTException $e) {
            report($e);
            return $this->error('Token refresh failed', 401);
        }
    }

    /**
     * Token response format
     */
    protected function respondWithToken(string $token, string $message)
    {
        return $this->success([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth()->factory()->getTTL() * 60,
        ], $message);
    }
}
