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
            $socialUser = Socialite::driver('facebook')->user();
            $shortToken = $socialUser->token;

            // 1. Exchange for Long-Lived Token (60 days)
            $longToken = $this->facebookService->exchangeToken($shortToken);

            // 2. Bulk Fetch Pages (Tokens will be Permanent/Long-Lived)
            $pages = $this->facebookService->getPages($longToken);

            // 3. Save to DB
            $user = Auth::user(); 
            
            // DEMO ONLY: If not logged in, just pick the first user to make it work for demo purposes
            if (!$user) {
                $user = User::first();
                if ($user) Auth::login($user);
            }

            if (!$user) {
                 return redirect('/')->with('error', 'Demo Error: No user found in DB. Please register a user first.');
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

            return redirect('/dashboard')->with('success', "Success! Connected {$count} Pages using Bulk Onboarding.");

        } catch (\Exception $e) {
            return redirect('/dashboard')->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
