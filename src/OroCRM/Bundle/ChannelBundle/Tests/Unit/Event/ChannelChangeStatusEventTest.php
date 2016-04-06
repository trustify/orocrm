<?php

namespace OroCRM\Bundle\ChannelBundle\Tests\Unit\Event;

use OroCRM\Bundle\ChannelBundle\Event\ChannelChangeStatusEvent;
use OroCRM\Bundle\ChannelBundle\Entity\Channel;

class ChannelChangeStatusEventTest extends ChannelEventAbstractTest
{
    
    public function testConstructorRequiresChannel()
    {
        if($this->getPhpVersion() < self::PHP_VERSION_7) {
            $this->setExpectedException('PHPUnit_Framework_Error');
        } else {
            $this->setExpectedException('TypeError');
        }

        $channel = new ChannelChangeStatusEvent(null);
    }

    public function testGetter()
    {
        $channel = new Channel();
        $event   = new ChannelChangeStatusEvent($channel);

        $this->assertSame($channel, $event->getChannel());
    }
}
