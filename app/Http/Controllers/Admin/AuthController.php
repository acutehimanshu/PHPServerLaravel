<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuthLogAdmin;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Admin Login
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

            if (! $token = auth('admin')->attempt($request->only('email', 'password'))) {
                AuthLogAdmin::create([
                    'email'      => $request->email,
                    'action'     => 'admin_login',
                    'status'     => 'failed',
                    'ip_address' => $request->ip(),
                    'message'    => 'Invalid credentials',
                ]);

                DB::commit();

                return $this->error('Invalid email or password', 401);
            }

            /** @var Admin $admin */
            $admin = auth('admin')->user();

            if ($admin->status !== 'active') {
                DB::commit();
                return $this->error('Account is not active', 403);
            }

            $admin->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            AuthLogAdmin::create([
                'admin_id'     => $admin->id,
                'email'       => $admin->email,
                'action'      => 'admin_login',
                'status'      => 'success',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'device_info' => is_array($request->device) ? $request->device : null,
            ]);

            DB::commit();

            if (config('services.email.enabled', false)) {
                EmailService::send(
                    $admin,
                    'Admin Login Alert',
                    'You have logged in to the admin panel.'
                );
            }

            if (config('services.sms.enabled', false)) {
                SmsService::send($admin, 'Admin login successful.');
            }

            return $this->respondWithToken($token, 'Admin login successful');

        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error('Admin login failed', 500);
        }
    }

    /**
     * Get authenticated admin
     */
    public function me()
    {
        try {
            return $this->success(
                auth('admin')->user(),
                'Admin profile retrieved'
            );
        } catch (TokenExpiredException $exception) {
            return $this->error('Token has expired', 401);
        } catch (TokenInvalidException $exception) {
            return $this->error('Token is invalid', 401);
        } catch (JWTException $exception) {
            return $this->error('Token not found', 401);
        }
    }

    /**
     * Logout admin
     */
    public function logout()
    {
        try {
            AuthLogAdmin::create([
                'admin_id' => auth('admin')->id(),
                'action'  => 'admin_logout',
                'status'  => 'success',
            ]);

            auth('admin')->logout();

            return $this->success(null, 'Successfully logged out');
        } catch (JWTException $exception) {
            report($exception);
            return $this->error('Logout failed', 401);
        }
    }

    /**
     * Refresh admin JWT token
     */
    public function refresh()
    {
        try {
            $token = auth('admin')->refresh();

            return $this->respondWithToken(
                $token,
                'Token refreshed successfully'
            );
        } catch (TokenExpiredException | TokenInvalidException | JWTException $exception) {
            report($exception);
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
            'expires_in'   => auth('admin')->factory()->getTTL() * 60,
        ], $message);
    }
}
