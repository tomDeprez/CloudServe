<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed_password');

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('test@example.com', $user->getUserIdentifier());
        $this->assertTrue($user->isActive());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testDefaultQuota(): void
    {
        $user = new User();

        // Quota par défaut = 2 GB = 2147483648 octets
        $this->assertEquals('2147483648', $user->getQuota());
        $this->assertEquals('0', $user->getUsedSpace());
    }

    public function testHasAvailableSpace(): void
    {
        $user = new User();
        $user->setQuota('1000');
        $user->setUsedSpace('500');

        // 500 + 400 = 900 < 1000 → OK
        $this->assertTrue($user->hasAvailableSpace(400));

        // 500 + 600 = 1100 > 1000 → NOK
        $this->assertFalse($user->hasAvailableSpace(600));
    }

    public function testAdminRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testUserInactivation(): void
    {
        $user = new User();
        $this->assertTrue($user->isActive());

        $user->setActive(false);
        $this->assertFalse($user->isActive());
    }
}
