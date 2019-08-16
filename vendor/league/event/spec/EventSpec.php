<?php

namespace spec\League\Event;

use League\Event\Emitter;
use PhpSpec\ObjectBehavior;

class EventSpec extends ObjectBehavior
{
    public function let()
    {
        $this->beConstructedWith('name');
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType('League\Event\Event');
        $this->shouldHaveType('League\Event\AbstractEvent');
        $this->shouldHaveType('League\Event\EventInterface');
    }

    public function it_should_have_a_name()
    {
        $this->getName()->shouldReturn('name');
    }

    public function it_should_allow_propagation_to_be_stopped()
    {
        $this->stopPropagation()->shouldReturn($this);
        $this->isPropagationStopped()->shouldReturn(true);
    }

    public function it_should_expose_an_emitter(Emitter $emitter)
    {
        $this->setEmitter($emitter);
        $this->getEmitter()->shouldReturn($emitter);
    }
}
