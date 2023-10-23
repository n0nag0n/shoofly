<?php
use PHPUnit\Framework\TestCase;
use Shoofly\Base;
use Shoofly\Auth;
use Shoofly\Database\Jig;
use Shoofly\Registry;

class AuthTest extends TestCase
{

	public function tearDown(): void {
		if (!is_dir('tmp/')) {
            mkdir('tmp/', Base::MODE, true);
        }

		Registry::clear(Base::class);
	}

    public function testHTTPBasicAuthMechanism()
    {
        $f3 = Base::instance();
		$f3->SERVER['PHP_AUTH_USER'] = 'admin';
		$f3->SERVER['PHP_AUTH_PW'] = 'secret';

        $db = new Jig('tmp/');
        $db->drop();
        $user = new Jig\Mapper($db, 'users');
        $user->set('user_id', 'admin');
        $user->set('password', 'secret');
        $user->save();
        $auth = new Auth($user, ['id' => 'user_id', 'pw' => 'password']);

        $this->assertTrue($auth->basic());
    }

    public function testLoginAuthMechanismWithJigStorage()
    {

        if (!is_dir('tmp/')) {
            mkdir('tmp/', Base::MODE, true);
        }

        $db = new Jig('tmp/');
        $db->drop();
        $user = new Jig\Mapper($db, 'users');
        $user->set('user_id', 'admin');
        $user->set('password', 'secret');
        $user->save();
        $user->reset();
        $user->set('user_id', 'superadmin');
        $user->set('password', password_hash('supersecret', PASSWORD_BCRYPT));
        $user->save();
        $auth = new Auth($user, ['id' => 'user_id', 'pw' => 'password']);

        $this->assertTrue($auth->login('admin', 'secret') && !$auth->login('user', 'what'));
    }

    public function testLoginAuthMechanismWithPasswordVerifyAndJigStorage()
    {

        if (!is_dir('tmp/')) {
            mkdir('tmp/', Base::MODE, true);
        }

        $db = new Jig('tmp/');
        $db->drop();
        $user = new Jig\Mapper($db, 'users');
        $user->set('user_id', 'superadmin');
        $user->set('password', password_hash('supersecret', PASSWORD_BCRYPT));
        $user->save();

        $auth = new Auth($user, ['id' => 'user_id', 'pw' => 'password'], function ($pw, $hash) {
            return password_verify($pw, $hash);
        });

        $this->assertTrue($auth->login('superadmin', 'supersecret') && !$auth->login('user', 'what'));
    }
}