<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        \Illuminate\Validation\Rules\Password::defaults(function () {
            return \Illuminate\Validation\Rules\Password::min(6);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
                
                if (!empty($settings['smtp_username']) && !empty($settings['smtp_password'])) {
                    config([
                        'mail.mailers.smtp.transport' => 'smtp',
                        'mail.mailers.smtp.host' => 'smtp.gmail.com',
                        'mail.mailers.smtp.port' => 465,
                        'mail.mailers.smtp.encryption' => 'tls',
                        'mail.mailers.smtp.username' => $settings['smtp_username'],
                        'mail.mailers.smtp.password' => $settings['smtp_password'],
                        'mail.from.address' => !empty($settings['smtp_from_address']) ? $settings['smtp_from_address'] : $settings['smtp_username'],
                        'mail.from.name' => $settings['company_commercial_name'] ?? 'LunAvalos',
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Ignore if DB not ready
        }
    }
}
