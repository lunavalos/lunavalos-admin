<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\SignatureTemplate;

class SignatureTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $html = '<table cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, sans-serif; color: #333; line-height: 1.4;">
  <tr>
    <!-- Logo Column -->
    <td style="padding-right: 25px; vertical-align: middle;">
      <img src="{{logo}}" width="140" style="display: block;">
    </td>
    
    <!-- Info Column -->
    <td style="padding-left: 25px; border-left: 2px solid #eee; vertical-align: top;">
      <table cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td style="padding-bottom: 15px;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding-right: 15px;">
                    <img src="{{photo}}" width="60" height="60" style="border-radius: 50%; display: block; object-fit: cover;">
                </td>
                <td>
                    <div style="font-size: 22px; font-weight: bold; color: #000; margin: 0;">{{name}}</div>
                    <div style="font-size: 14px; color: #264ab3; font-weight: bold; margin-top: 2px;">{{position}}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="font-size: 13px; color: #444;">
            <table cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
              <tr>
                <td style="padding-bottom: 5px;"><span style="color: #264ab3; font-weight: bold; margin-right: 8px;">📞</span> {{phone}}</td>
              </tr>
              <tr>
                <td style="padding-bottom: 5px;"><span style="color: #264ab3; font-weight: bold; margin-right: 8px;">✉️</span> {{email}}</td>
              </tr>
              <tr>
                <td style="padding-bottom: 5px;"><span style="color: #264ab3; font-weight: bold; margin-right: 8px;">🌐</span> {{website}}</td>
              </tr>
              <tr>
                <td style="padding-top: 5px;"><span style="color: #264ab3; font-weight: bold; margin-right: 8px;">📋</span> <a href="#" style="color: #264ab3; text-decoration: none; font-weight: bold; font-style: italic;">Take our survey</a></td>
              </tr>
              <tr>
                <td>{{social_links}}</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
    
    <!-- QR Column -->
    <td style="padding-left: 40px; vertical-align: middle;">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{website}}" width="80" style="display: block;">
    </td>
  </tr>
  <!-- Bottom Bar -->
  <tr>
    <td colspan="3" style="padding-top: 20px;">
        <table cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
            <tr>
                <td style="width: 70%; height: 18px; background-color: #0046ad; border-top-left-radius: 10px; border-bottom-left-radius: 10px;"></td>
                <td style="width: 5%; height: 18px;"></td>
                <td style="width: 25%; height: 18px; background-color: #939598; border-top-right-radius: 10px; border-bottom-right-radius: 10px;"></td>
            </tr>
        </table>
    </td>
  </tr>
</table>';

        SignatureTemplate::updateOrCreate(
            ['slug' => 'absolute-group-modern'],
            [
                'name' => 'Absolute Group Modern',
                'html_content' => $html,
                'is_active' => true,
            ]
        );
    }
}
