<?php

namespace Shoofly\Tests;

use PHPUnit\Framework\TestCase;
use Shoofly\Base;
use Shoofly\Registry;
use Shoofly\Basket;

class BasketTest extends TestCase
{
    public function tearDown(): void
    {
        if (!is_dir('tmp/')) {
            mkdir('tmp/', Base::MODE, true);
        }
        Base::instance()->SESSION = [];
        Registry::clear(Base::class);
    }

    public function testCursorInstantiated()
    {
        $basket = new Basket();
        $this->assertInstanceOf(Basket::class, $basket);
    }

    public function testItemSaved()
    {
        $basket = new Basket();
        $basket->set('item', 'chicken wings');
        $basket->set('quantity', 3);
        $basket->set('price', 0.68);
        $basket->set('measure', 'pound');
        $basket->save();
        $this->assertTrue(
            $basket->get('item') == 'chicken wings' &&
            $basket->get('quantity') == 3 &&
            $basket->get('price') == 0.68 &&
            $basket->get('measure') == 'pound'
        );
    }

    public function testCurrentBasketItemEmptyOrUndefined()
    {
        $basket = new Basket();
        $basket->load('item', 'port wine');
        $this->assertTrue($basket->dry());
    }

    public function testItemAdded()
    {
        $basket = new Basket();
        $basket->set('item', 'port wine');
        $basket->set('quantity', 1);
        $basket->set('price', 8.65);
        $basket->set('measure', 'bottle');
        $basket->save();
        $this->assertTrue(
            $basket->get('item') == 'port wine' &&
            $basket->get('quantity') == 1 &&
            $basket->get('price') == 8.65 &&
            $basket->get('measure') == 'bottle'
        );
    }

    public function testFirstItemUpdated()
    {
        $basket = new Basket();
        $basket->load('item', 'chicken wings');
        $basket->set('quantity', 2);
        $basket->save();
        $basket->reset();
        $basket->set('item', 'lamb chops');
        $basket->set('quantity', 1);
        $basket->set('price', 99.95);
        $basket->set('measure', 'pack of 8');
        $basket->save();
        $this->assertTrue(
            $basket->get('item') == 'lamb chops' &&
            $basket->get('quantity') == 1 &&
            $basket->get('price') == 99.95 &&
            $basket->get('measure') == 'pack of 8'
        );
    }

    public function testAnotherItemAdded()
    {
        $basket = new Basket();
        $basket->set('item', 'lamb chops');
        $basket->set('quantity', 1);
        $basket->set('price', 99.95);
        $basket->set('measure', 'pack of 8');
        $basket->save();
        $basket->reset();
        $basket->set('item', 'blue cheese');
        $basket->set('quantity', 1);
        $basket->set('price', 7.50);
        $basket->set('measure', '12oz');
        $basket->save();
        $this->assertEquals(2, $basket->count());
    }

    public function testCurrentItemSurvives()
    {
        $basket = new Basket();
        $basket->erase('item', 'port wine');
        $this->assertNull($basket->get('_id'));
    }

    public function testCurrentItemCopiedToHiveVariable()
    {
        $basket = new Basket();
        $f3 = Base::instance();
        $basket->copyto('foo');
        $this->assertEquals(
            $f3->get('foo.item'),
            'blue cheese' &&
            $f3->get('foo.quantity'),
            1 &&
            $f3->get('foo.price'),
            7.50 &&
            $f3->get('foo.measure'),
            '12oz'
        );
    }

    public function testLoadItemById()
    {
        $basket = new Basket();
        $basket->item = 'lamb chops';
        $basket->quantity = 1;
        $basket->price = 99.95;
        $basket->measure = 'pack of 8';
        $basket->save();
        $id = $basket->_id;
        $basket->reset();
        $basket->load('_id', $id);
        $this->assertTrue(
            $basket->get('item') == 'lamb chops' &&
            $basket->get('quantity') == 1 &&
            $basket->get('price') == 99.95 &&
            $basket->get('measure') == 'pack of 8'
        );
    }

    public function testCheckOut()
    {
        $basket = new Basket();
        $basket->item = 'lamb chops';
        $basket->quantity = 1;
        $basket->price = 99.95;
        $basket->measure = 'pack of 8';
        $basket->save();
        $this->assertEquals(
            array_values($basket->checkout()),
            [
                [
                    'item' => 'lamb chops',
                    'quantity' => 1,
                    'price' => 99.95,
                    'measure' => 'pack of 8',
                ],
            ]
        );
    }

    public function testMagicAccess()
    {
        $basket = new Basket();
        $basket->item = 'Chocolate cake';
        $this->assertEquals($basket->item, 'Chocolate cake');
    }
}
