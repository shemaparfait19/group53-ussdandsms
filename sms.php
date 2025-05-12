<?php
require 'vendor/autoload.php';

use AfricasTalking\SDK\AfricasTalking;

class sms {
    protected $phone;
    protected $AT;

    function __construct($phone) {
        // Initialize Africa's Talking gateway with correct credentials
        $this->phone = $phone;
        $this->AT = new AfricasTalking('sandbox', 'atsk_c3f383b1cb8999570a80f94546baf520d4a61d7ede2a3c98ba8c61234d3ccd91e3ae0a61');
    }

    public function sendSMS($message, $recipients) {
        $sms = $this->AT->sms();

        try {
            // Don't include 'from' in sandbox
            $result = $sms->send([
                'to'      => $recipients,
                'message' => $message
            ]);
            return $result;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}

// // Sample test
// $phone = "+250786139330"; // Use verified sandbox number
// $message = "Test SMS from Africa's Talking Sandbox.";
// $recipients = "+250786139330"; // Must be sandbox-registered number

// $smsInstance = new sms($phone);
// $result = $smsInstance->sendSMS($message, $recipients);

// print_r($result);
?>