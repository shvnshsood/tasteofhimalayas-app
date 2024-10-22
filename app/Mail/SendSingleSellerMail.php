<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendSingleSellerMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $message;
    public function __construct($subject, $message)
    {
        $this->subject = $subject;
        $this->message = $message;
    }

    public function build()
    {
        $subject = $this->subject;
        $htmlContent = $this->message;

        return $this->subject($this->subject)->view('admin.send_single_seller_email_template',compact('htmlContent','subject'));
    }
}
