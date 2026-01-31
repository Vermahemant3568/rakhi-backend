<?php

namespace App\Services\Safety;

class SafeResponses
{
    public static function emergency(): string
    {
        return "I'm really glad you reached out. What you're describing could be serious. "
             . "I'm not a doctor, and I can't help with emergencies. "
             . "Please contact a medical professional or emergency services immediately. "
             . "If possible, ask someone nearby for help right now.";
    }

    public static function disclaimer(): string
    {
        return "I'm here to support you with lifestyle and wellness guidance, "
             . "but I can't diagnose or treat medical conditions. "
             . "For medical advice, it's important to consult a qualified doctor.";
    }
}