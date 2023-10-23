<?php
use PHPUnit\Framework\TestCase;
use Shoofly\Audit;
use Shoofly\Base;

class AuditTest extends TestCase
{
    public function testURL()
    {
        $audit = new Audit();
        $this->assertFalse($audit->url('http://www.example.com/space here.html'));
        $this->assertTrue($audit->url('http://www.example.com/space%20here.html'));
    }

    public function testEmailAddress()
    {
        $audit = new Audit();
        $this->assertFalse($audit->email('Abc.google.com', false));
        $this->assertFalse($audit->email('Abc.@google.com', false));
        $this->assertFalse($audit->email('Abc..123@google.com', false));
        $this->assertFalse($audit->email('A@b@c@google.com', false));
        $this->assertFalse($audit->email('a"b(c)d,e:f;g<h>i[j\k]l@google.com', false));
        $this->assertFalse($audit->email('just"not"right@google.com', false));
        $this->assertFalse($audit->email('this is"not\allowed@google.com', false));
        $this->assertFalse($audit->email('this\ still\"not\\allowed@google.com', false));
        $this->assertTrue($audit->email('niceandsimple@google.com', false));
        $this->assertTrue($audit->email('very.common@google.com', false));
        $this->assertTrue($audit->email('a.little.lengthy.but.fine@google.com', false));
        $this->assertTrue($audit->email('disposable.email.with+symbol@google.com', false));
        $this->assertTrue($audit->email('user@[IPv6:2001:db8:1ff::a0b:dbd0]', false));
        $this->assertTrue($audit->email('"very.unusual.@.unusual.com"@google.com', false));
        $this->assertTrue($audit->email('!#$%&\'*+-/=?^_`{}|~@google.com', false));
        $this->assertTrue($audit->email('""@google.com', false));
    }

    public function testEmailAddressWithDomainVerification()
    {
        $audit = new Audit();
        $this->assertFalse($audit->email('Abc.google.com'));
        $this->assertFalse($audit->email('Abc.@google.com'));
        $this->assertFalse($audit->email('Abc..123@google.com'));
        $this->assertFalse($audit->email('A@b@c@google.com'));
        $this->assertFalse($audit->email('a"b(c)d,e:f;g<h>i[j\k]l@google.com'));
        $this->assertFalse($audit->email('just"not"right@google.com'));
        $this->assertFalse($audit->email('this is"not\allowed@google.com'));
        $this->assertFalse($audit->email('this\ still\"not\\allowed@google.com'));
        $this->assertTrue($audit->email('niceandsimple@google.com'));
        $this->assertTrue($audit->email('very.common@google.com'));
        $this->assertTrue($audit->email('a.little.lengthy.but.fine@google.com'));
        $this->assertTrue($audit->email('disposable.email.with+symbol@google.com'));
        $this->assertTrue($audit->email('user@[IPv6:2001:db8:1ff::a0b:dbd0]', false));
        $this->assertTrue($audit->email('"very.unusual.@.unusual.com"@google.com'));
        $this->assertTrue($audit->email('!#$%&\'*+-/=?^_`{}|~@google.com'));
        $this->assertTrue($audit->email('""@google.com'));
    }

    public function testIPv4Address()
    {
        $audit = new Audit();
        $this->assertFalse($audit->ipv4(''));
        $this->assertFalse($audit->ipv4('...'));
        $this->assertFalse($audit->ipv4('hello, world'));
        $this->assertFalse($audit->ipv4('256.256.0.0'));
        $this->assertFalse($audit->ipv4('255.255.255.'));
        $this->assertFalse($audit->ipv4('.255.255.255'));
        $this->assertFalse($audit->ipv4('172.300.256.100'));
        $this->assertTrue($audit->ipv4('30.88.29.1'));
        $this->assertTrue($audit->ipv4('192.168.100.48'));
    }

    public function testIPv6Address()
    {
        $audit = new Audit();
        $this->assertFalse($audit->ipv6(''));
        $this->assertFalse($audit->ipv6('FF01::101::2'));
        $this->assertFalse($audit->ipv6('::1.256.3.4'));
        $this->assertFalse($audit->ipv6('2001:DB8:0:0:8:800:200C:417A:221'));
        $this->assertFalse($audit->ipv6('FF02:0000:0000:0000:0000:0000:0000:0000:0001'));
        $this->assertTrue($audit->ipv6('::'));
        $this->assertTrue($audit->ipv6('::1'));
        $this->assertTrue($audit->ipv6('2002::'));
        $this->assertTrue($audit->ipv6('::ffff:192.0.2.128'));
        $this->assertTrue($audit->ipv6('0:0:0:0:0:0:0:1'));
        $this->assertTrue($audit->ipv6('2001:DB8:0:0:8:800:200C:417A'));
    }

    public function testLocalIPRange()
    {
        $audit = new Audit();
        $this->assertFalse($audit->isprivate('0.1.2.3'));
        $this->assertFalse($audit->isprivate('201.176.14.4'));
        $this->assertTrue($audit->isprivate('fc00::'));
        $this->assertTrue($audit->isprivate('10.10.10.10'));
        $this->assertTrue($audit->isprivate('172.16.93.7'));
        $this->assertTrue($audit->isprivate('192.168.3.5'));
    }

    public function testReservedIPRange()
    {
        $audit = new Audit();
        $this->assertFalse($audit->isreserved('193.194.195.196'));
        $this->assertTrue($audit->isreserved('::1'));
        $this->assertTrue($audit->isreserved('127.0.0.1'));
        $this->assertTrue($audit->isreserved('0.1.2.3'));
        $this->assertTrue($audit->isreserved('169.254.1.2'));
		$this->assertFalse($audit->isreserved('192.0.2.1'));
		$this->assertFalse($audit->isreserved('192.168.0.1'));
        $this->assertFalse($audit->isreserved('224.225.226.227'));
        $this->assertTrue($audit->isreserved('240.241.242.243'));
    }

    public function testCardTypes()
    {
        $audit = new Audit();

        $type = 'American Express';
        $this->assertEquals($type, $audit->card('378282246310005'));
        $this->assertEquals($type, $audit->card('371449635398431'));
        $this->assertEquals($type, $audit->card('378734493671000'));

        $type = 'Diners Club';
        $this->assertEquals($type, $audit->card('30569309025904'));
        $this->assertEquals($type, $audit->card('38520000023237'));

        $type = 'Discover';
        $this->assertEquals($type, $audit->card('6011111111111117'));
        $this->assertEquals($type, $audit->card('6011000990139424'));

        $type = 'JCB';
        $this->assertEquals($type, $audit->card('3530111333300000'));
        $this->assertEquals($type, $audit->card('3566002020360505'));

        $type = 'MasterCard';
        $this->assertEquals($type, $audit->card('5555555555554444'));
        $this->assertEquals($type, $audit->card('2221000010000015'));
        $this->assertEquals($type, $audit->card('5105105105105100'));

        $type = 'Visa';
        $this->assertEquals($type, $audit->card('4222222222222'));
        $this->assertEquals($type, $audit->card('4111111111111111'));
        $this->assertEquals($type, $audit->card('4012888888881881'));
    }

    public function testIsDesktop()
    {
        $audit = new Audit();
        $this->assertTrue($audit->isdesktop('Linux Mozilla User Agent 4.0'));
    }

    public function testIsMobile()
    {
        global $f3;
        $audit = new Audit();
		$this->assertTrue($audit->ismobile('iPhone Mozilla User Agent 4.0'));
    }

}
