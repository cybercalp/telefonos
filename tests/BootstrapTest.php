<?php
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testBootstrap(): void
    {
        $this->assertTrue(class_exists(\BaconQrCode\Encoder\Encoder::class));
    }
}
