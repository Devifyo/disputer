<?php

namespace App\Livewire\Admin\Users\Traits;

use App\Models\UserEmailConfig;

trait ManagesEmailConfig
{
    // Checkbox State
    public $has_mail_config = false; 

    // Email Config Properties
    public $smtp_host, $smtp_port = 587, $smtp_username, $smtp_password, $smtp_encryption = 'tls';
    public $imap_host, $imap_port = 993, $imap_username, $imap_password, $imap_encryption = 'ssl';
    public $from_name, $from_email;

    /**
     * Mail Config Validation Rules
     */
    protected function getEmailConfigRules()
    {
        return [
            'has_mail_config' => 'boolean',
            
            // SMTP
            'smtp_host' => 'required_if:has_mail_config,true|nullable|string',
            'smtp_port' => 'required_if:has_mail_config,true|nullable|numeric',
            'smtp_username' => 'required_if:has_mail_config,true|nullable|string',
            'smtp_password' => 'required_if:has_mail_config,true|nullable|string',
            
            // IMAP 
            'imap_host' => 'required_if:has_mail_config,true|nullable|string',
            'imap_port' => 'required_if:has_mail_config,true|nullable|numeric',
            'imap_username' => 'required_if:has_mail_config,true|nullable|string',
            'imap_password' => 'required_if:has_mail_config,true|nullable|string',
            
            // Sender Details
            'from_name' => 'required_if:has_mail_config,true|nullable|string',
            'from_email' => 'required_if:has_mail_config,true|nullable|email',
        ];
    }

    /**
     * Mail Config Validation Messages
     */
    protected function getEmailConfigMessages()
    {
        return [
            'from_name.required_if' => 'Sender name is required.',
            'from_email.required_if' => 'Sender email is required.',
            'from_email.email' => 'Please provide a valid sender email address.',
            'smtp_host.required_if' => 'SMTP Host is required (e.g., smtp.gmail.com).',
            'smtp_port.required_if' => 'SMTP Port is required (e.g., 587).',
            'smtp_username.required_if' => 'SMTP Username is required.',
            'smtp_password.required_if' => 'SMTP Password is required.',
            'imap_host.required_if' => 'IMAP Host is required (e.g., imap.gmail.com).',
            'imap_port.required_if' => 'IMAP Port is required (e.g., 993).',
            'imap_username.required_if' => 'IMAP Username is required.',
            'imap_password.required_if' => 'IMAP Password is required.',
        ];
    }

    /**
     * Load config into component properties
     */
    public function loadEmailConfig($user)
    {
        if ($config = $user->emailConfig) {
            $this->has_mail_config = true;
            $this->smtp_host = $config->smtp_host;
            $this->smtp_port = $config->smtp_port;
            $this->smtp_username = $config->smtp_username;
            $this->smtp_password = $config->smtp_password;
            $this->smtp_encryption = $config->smtp_encryption;
            $this->imap_host = $config->imap_host;
            $this->imap_port = $config->imap_port;
            $this->imap_username = $config->imap_username;
            $this->imap_password = $config->imap_password;
            $this->imap_encryption = $config->imap_encryption;
            $this->from_name = $config->from_name;
            $this->from_email = $config->from_email;
        } else {
            $this->resetEmailConfig();
        }
    }

    /**
     * Save or delete config based on checkbox
     */
    public function saveEmailConfig($user)
    {
        if (!$this->has_mail_config) {
            UserEmailConfig::where('user_id', $user->id)->delete();
            return;
        }

        UserEmailConfig::updateOrCreate(
            ['user_id' => $user->id],
            [
                'smtp_host' => $this->smtp_host,
                'smtp_port' => $this->smtp_port,
                'smtp_username' => $this->smtp_username,
                'smtp_password' => $this->smtp_password,
                'smtp_encryption' => $this->smtp_encryption,
                'imap_host' => $this->imap_host,
                'imap_port' => $this->imap_port,
                'imap_username' => $this->imap_username,
                'imap_password' => $this->imap_password,
                'imap_encryption' => $this->imap_encryption,
                'from_name' => $this->from_name,
                'from_email' => $this->from_email,
            ]
        );
    }

    /**
     * Reset config properties to defaults
     */
    public function resetEmailConfig()
    {
        $this->has_mail_config = false;
        $this->smtp_host = $this->smtp_username = $this->smtp_password = $this->imap_host = $this->imap_username = $this->imap_password = $this->from_name = $this->from_email = null;
        $this->smtp_port = 587;
        $this->smtp_encryption = 'tls';
        $this->imap_port = 993;
        $this->imap_encryption = 'ssl';
    }
}