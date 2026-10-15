<?php

use App\Models\User;
use App\Settings\BackupSettings;
use App\Settings\EmailSettings;
use Livewire\Livewire;

it('does not expose persisted S3 secrets and preserves them when the field is left blank', function () {
    $user = User::factory()->create();
    $settings = app(BackupSettings::class);
    $settings->enabled = true;
    $settings->frequency = 'daily';
    $settings->time = '02:00';
    $settings->day_of_week = 1;
    $settings->day_of_month = 1;
    $settings->aws_access_key_id = 'AKIA_TEST';
    $settings->aws_secret_access_key = 's3-secret-that-must-not-reach-the-browser';
    $settings->aws_default_region = 'eu-west-1';
    $settings->aws_bucket = 'fatturino-backups';
    $settings->aws_endpoint = null;
    $settings->aws_use_path_style_endpoint = false;
    $settings->save();

    $this->actingAs($user);

    Livewire::test('pages::settings.services')
        ->assertDontSee('s3-secret-that-must-not-reach-the-browser')
        ->assertSet('aws_secret_access_key', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(BackupSettings::class)->aws_secret_access_key)->toBe('s3-secret-that-must-not-reach-the-browser');
});

it('does not expose persisted email secrets and preserves them when the fields are left blank', function () {
    $user = User::factory()->create();
    $settings = app(EmailSettings::class);
    $settings->mail_provider = 'smtp';
    $settings->smtp_host = 'smtp.example.test';
    $settings->smtp_port = 587;
    $settings->smtp_username = 'mailer';
    $settings->smtp_password = 'smtp-secret-that-must-not-reach-the-browser';
    $settings->smtp_encryption = 'tls';
    $settings->scaleway_tem_region = 'fr-par';
    $settings->scaleway_tem_project_id = 'project-id';
    $settings->scaleway_tem_secret_key = 'scaleway-secret-that-must-not-reach-the-browser';
    $settings->from_address = 'billing@example.test';
    $settings->from_name = 'Fatturino';
    $settings->template_sales_subject = '';
    $settings->template_sales_body = '';
    $settings->template_proforma_subject = '';
    $settings->template_proforma_body = '';
    $settings->auto_send_sales = false;
    $settings->auto_send_proforma = false;
    $settings->save();

    $this->actingAs($user);

    Livewire::test('pages::settings.email')
        ->assertDontSee('smtp-secret-that-must-not-reach-the-browser')
        ->assertDontSee('scaleway-secret-that-must-not-reach-the-browser')
        ->assertSet('smtp_password', '')
        ->assertSet('scaleway_tem_secret_key', '')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(EmailSettings::class);
    expect($settings->smtp_password)->toBe('smtp-secret-that-must-not-reach-the-browser')
        ->and($settings->scaleway_tem_secret_key)->toBe('scaleway-secret-that-must-not-reach-the-browser');
});
