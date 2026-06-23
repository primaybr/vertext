<?php

declare(strict_types=1);

namespace App\Mail;

class MailMessage
{
    private string $to        = '';
    private string $toName    = '';
    private string $subject   = '';
    private string $htmlBody  = '';
    private string $textBody  = '';
    private string $fromEmail = '';
    private string $fromName  = '';
    private string $replyTo   = '';

    public function to(string $email, string $name = ''): self
    {
        $this->to     = $email;
        $this->toName = $name;
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function htmlBody(string $html): self
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function textBody(string $text): self
    {
        $this->textBody = $text;
        return $this;
    }

    public function from(string $email, string $name = ''): self
    {
        $this->fromEmail = $email;
        $this->fromName  = $name;
        return $this;
    }

    public function replyTo(string $email): self
    {
        $this->replyTo = $email;
        return $this;
    }

    public function getTo(): string        { return $this->to; }
    public function getToName(): string    { return $this->toName; }
    public function getSubject(): string   { return $this->subject; }
    public function getHtmlBody(): string  { return $this->htmlBody; }
    public function getTextBody(): string  { return $this->textBody; }
    public function getFromEmail(): string { return $this->fromEmail; }
    public function getFromName(): string  { return $this->fromName; }
    public function getReplyTo(): string   { return $this->replyTo; }
}
