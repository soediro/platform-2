<?php

namespace Oro\Bundle\LoggerBundle\Tests\Unit\Mailer;

use Oro\Bundle\LoggerBundle\Mailer\NoRecipientPlugin;

class NoRecipientPluginTest extends \PHPUnit\Framework\TestCase
{
    public function testBeforeSendPerformedWithoutRecipient()
    {
        $message = new \Swift_Message();

        $event = $this->createMock(\Swift_Events_SendEvent::class);
        $event->expects($this->once())
            ->method('getMessage')
            ->willReturn($message);

        $event->expects($this->once())
            ->method('cancelBubble');

        $plugin = new NoRecipientPlugin();
        $plugin->beforeSendPerformed($event);
    }

    public function testBeforeSendPerformedWithRecipient()
    {
        $message = new \Swift_Message();
        $message->setTo('recipient@example.com');

        $event = $this->createMock(\Swift_Events_SendEvent::class);
        $event->expects($this->once())
            ->method('getMessage')
            ->willReturn($message);

        $event->expects($this->never())
            ->method('cancelBubble');

        $plugin = new NoRecipientPlugin();
        $plugin->beforeSendPerformed($event);
    }
}
