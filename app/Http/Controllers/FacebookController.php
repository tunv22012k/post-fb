<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Services\FacebookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;

class FacebookController extends Controller
{
    protected $facebookService;

    public function __construct(FacebookService $facebookService)
    {
        $this->facebookService = $facebookService;
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')
            ->scopes(['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'])
            ->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            \Illuminate\Support\Facades\Log::info('Facebook Callback Started');
            $socialUser = Socialite::driver('facebook')->user();
            \Illuminate\Support\Facades\Log::info('Social User retrieved', ['id' => $socialUser->id, 'name' => $socialUser->name]);
            
            $shortToken = $socialUser->token;

            // 1. Exchange for Long-Lived Token (60 days)
            $longToken = $this->facebookService->exchangeToken($shortToken);
            \Illuminate\Support\Facades\Log::info('Long Lived Token exchanged');

            // 2. Bulk Fetch Pages (Tokens will be Permanent/Long-Lived)
            $pages = $this->facebookService->getPages($longToken);
            \Illuminate\Support\Facades\Log::info('Pages fetched', ['count' => count($pages), 'data' => $pages]);

            // 3. Save to DB
            $user = Auth::user(); 
            
            // Auto-login or Create User logic
            if (!$user) {
                // Try to find first user
                $user = User::first();

                // If no user exists in DB, create one
                if (!$user) {
                    $user = User::create([
                        'name' => $socialUser->name ?? 'Facebook User',
                        'email' => $socialUser->email ?? $socialUser->id . '@facebook.local',
                        'password' => \Illuminate\Support\Facades\Hash::make('password'),
                        'email_verified_at' => now(),
                    ]);
                    \Illuminate\Support\Facades\Log::info('Created new user', ['id' => $user->id]);
                }
                
                Auth::login($user);
                \Illuminate\Support\Facades\Log::info('Auto-logged in user', ['id' => $user->id]);
            }

            $count = 0;
            foreach ($pages as $page) {
                DB::table('destination_channels')->updateOrInsert(
                    ['platform_id' => $page['id']],
                    [
                        'user_id' => $user->id,
                        'name' => $page['name'],
                        'access_token' => $page['access_token'], // Token is now Permanent per page
                        'platform_type' => 'facebook_page',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
            \Illuminate\Support\Facades\Log::info('Pages saved', ['count' => $count]);

            return redirect('/dashboard')->with('success', "Success! Connected {$count} Pages using Bulk Onboarding.");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Facebook Callback Error: ' . $e->getMessage());
            return redirect('/dashboard')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
