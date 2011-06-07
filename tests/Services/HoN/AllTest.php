<?php
require_once 'PHPUnit/Autoload.php';
require_once '../Services/HoN.php';
/**
 *
 * AngryTestie  699935
 * CarDinaL     3879
 * Tralfamadore 1160294
 * withgod      3412506
 *
 */
class Services_HoN_AllTest extends PHPUnit_Framework_TestCase
{
    protected $api = null;
    public function setUp() {
        $this->api = new Services_HoN(null, null, false);
    }
    public function testNick2Id() {
        $result = $this->api->nick2id('withgod');
        $this->assertEquals(count($result), 1);
        $this->assertEquals($result['withgod'], 3412506);
        $result_multi = $this->api->nick2id(array('withgod', 'AngryTestie'));
        $this->assertEquals(count($result_multi), 2);
        $this->assertEquals($result_multi['withgod'], 3412506);
        $this->assertEquals($result_multi['AngryTestie'], 699935);
        $this->assertEquals(isset($result_multi['fizzbuzz']), false);

    }
    public function testId2Nick() {
        $result = $this->api->id2nick('3412506');
        $this->assertEquals(count($result), 1);
        $this->assertEquals($result['3412506'], 'withgod');
        $result_multi = $this->api->id2nick(array('3412506', '699935'));
        $this->assertEquals(count($result_multi), 2);
        $this->assertEquals($result_multi['3412506'], 'withgod');
        $this->assertEquals($result_multi['699935'], 'AngryTestie');
        $this->assertEquals(isset($result_multi['99999']), false);
    }
    public function testMatchHistory() {
        $this->assertEquals(count($this->api->history('withgod')->ranked()), 1);
        $this->assertEquals(count($this->api->history('withgod')->pub()),  1);
        //dont play casual mode
        $this->assertEquals(count($this->api->history('withgod')->casual()),  0);

        //testie's ranked match history dosent work... 2011/06/08
        //http://xml.heroesofnewerth.com/xml_requester.php?f=ranked_history&opt=nick&nick[]=AngryTestie
        $this->assertEquals(count($this->api->history(array('withgod', 'AngryTestie', 'CarDinaL', 'Tralfamadore'))->ranked()),  3);

        $this->assertTrue(true);
    }
    public function testPlayerStats() {
        $result = $this->api->playerStats('withgod');
        $this->assertEquals(count($result), 1);
        $this->assertEquals($result[0]['nickname'], 'withgod');

        $result_multi = $this->api->playerStats(array('withgod', 'AngryTestie', 'CarDinaL'));
        $this->assertEquals(count($result_multi), 3);
        $this->assertEquals($result_multi[0]['nickname'], 'AngryTestie');
        $this->assertEquals($result_multi[1]['nickname'], 'CarDinaL');
        $this->assertEquals($result_multi[2]['nickname'], 'withgod');

        $result_multi_missing = $this->api->playerStats(array('withgod', 'mr_withgod'));
        $this->assertEquals(count($result_multi_missing), 1);
        $this->assertEquals($result_multi_missing[0]['nickname'], 'withgod');
    }
}
?>
