<?php

require_once 'HTTP/Request2.php';

class Services_HoN
{
    protected $request       = null;
    protected $apiBase       = 'http://xml.heroesofnewerth.com/xml_requester.php';
    protected $target        = null;
    protected $lastRequest   = null;
    protected $lastResponse  = null;
    protected $lastException = null;

    /*
    protected $cachable      = false;
    protected $cacheProvider = 'file';
    protected $avaiableCacheProvider = array('memcached', 'file', 'memory');
    protected $cacheOption   = 'd=/tmp,ttl=300'; //'h=127.0.0.1:11211,ttl=60*60*5'
    protected $cacheLevel    = 1;//1 = string table, 9 = all(not yet)
    protected $cacheOnMemory = false;

        $cache           = false,
        $cacheProvider   = 'file',
        $cacheLevel      = 1,
     */

    protected $debug         = false;

    public function __construct(
        $request = null,
        $apiBase = null,
        $debug   = false
    ) {
        if (!empty($apiBase)) {
            $this->apiBase = $apiBase;
        }
        if (!empty($request)) {
            $this->request = $request;
        } else {
            $this->request = new HTTP_Request2();
            $_ua = 'Services_HoN/' . get_class($this)
                . ' PHP_VERSION/' . PHP_VERSION . ' PHP_OS/' . PHP_OS;
            $this->request->setHeader('User-Agent', $_ua);
        }
        $this->debug = $debug;
    }

    public function getRequest()
    {
        return $this->request;
    }

    protected function isNumericTargets($target = null)
    {
        if (!empty($target)) {
            $allNumeric = true;
            if (is_array($target)) {
                foreach ($target as $t) {
                    if (!preg_match('/^\d+$/', $t)) {
                        $allNumeric = false;
                    }
                }
                return $allNumeric;
            } else {
                if (preg_match('/^\d+$/', $target)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function buildQuery($f = null, $target = null)
    {
        $query   = 'f=' . $f . '&';
        $optname = '';
        if ($f == 'match_stats') {
            $optname = 'mid';
        } else if ($f == 'stringtable') {
            $optname = 'trans';
        } else if ($this->isNumericTargets($target)) {
            $optname = 'aid';
        } else {
            $optname = 'nick';
        }

        $query .= 'opt=' . $optname . '&' . $optname . '[]=';
        if (is_array($target)) {
            $query .= join('&' . $optname. '[]=', $target);
        } else {
            $query .= $target;
        }

        if ($this->debug) {
            var_dump(array('build query', $query));
        }
        return $query;
    }

    public function history($target = null)
    {
        $this->target = $target;
        return $this;
    }

    public function pub()
    {
        return $this->mhistory('public_history');
    }
    public function ranked()
    {
        return $this->mhistory('ranked_history');
    }
    public function casual()
    {
        return $this->mhistory('casual_history');
    }

    protected function mhistory($f = null)
    {
        $result = array();
        if (!empty($this->target)) {
            $param = $this->buildQuery($f, $this->target);
            $response = simplexml_load_string($this->doRequest($param));
            if (!empty($response)) {
                foreach ($response->$f as $history) {
                    $key = (string)$history->attributes()->aid;
                    $matches = array();
                    foreach ($history->match as $match) {
                        $matches[] = array(
                            'id'   => (string)$match->id,
                            'date' => (string)$match->date
                        );
                    }
                    $result[$key] = $matches;
                }
            }
        }
        return $result;
    }

    public function nick2id($target = null)
    {
        $result = array();
        if (!empty($target)) {
            $param = $this->buildQuery('nick2id', $target);
            $response = simplexml_load_string($this->doRequest($param));
            if (!empty($response)) {
                foreach ($response->accounts->account_id as $account) {
                    $nick = (string)$account->attributes()->nick;
                    $id   = (string)$account;
                    $result[$nick] = $id;
                }
            }
        }
        return $result;
    }

    public function id2nick($target = null)
    {
        $result = array();
        if (!empty($target)) {
            $param = $this->buildQuery('id2nick', $target);
            $response = simplexml_load_string($this->doRequest($param));
            if (!empty($response)) {
                foreach ($response->accounts->nickname as $nickname) {
                    $nick = (string)$nickname;
                    $id   = (string)$nickname->attributes()->aid;
                    $result[$id] = $nick;
                }
            }
        }
        return $result;
    }

    /**
     * team id 1 = legion
     * team id 2 = hellbourne
     */
    public function matchStats($target = null)
    {
        $result = array();
        if (!empty($target)) {
            $param = $this->buildQuery('match_stats', $target);
            $response = simplexml_load_string($this->doRequest($param));
            if (!empty($response)) {
                foreach ($response->stats->match as $match) {
                    $mid = (string)$match->attributes()->mid;
                    $tmp = array('legion' => array(), 'hellbourne' => array());
                    $_team_result = array(
                        'legion'     => array('players' => array()),
                        'hellbourne' => array('players' => array())
                    );
                    foreach ($match->team as $team) {
                        $team_name_tmp = (string)$team->attributes()->side;
                        $team_name  =  $team_name_tmp == 1 ? 'legion' : 'hellbourne';
                        $team_score = array();
                        foreach ($team->stat as $stat) {
                            $tmp = split('_', (string)$stat->attributes()->name);
                            $_key = strtolower($tmp[1]);
                            $team_score[$_key] = (string)$stat;
                        }
                        $_team_result[$team_name]['score'] = $team_score;
                    }
                    foreach ($match->match_stats->ms as $hero_stat) {
                        $tmp_hero_stat = array();
                        $tmp_hero_stat['aid'] = (string)
                            $hero_stat->attributes()->aid;
                        foreach ($hero_stat as $hero_stat_item) {
                            $_key   = (string)$hero_stat_item->attributes()->name;
                            $_value = (string)$hero_stat_item;
                            $tmp_hero_stat[$_key] = $_value;
                        }
                        $team_name  = $tmp_hero_stat['team'] == 1 ?
                            'legion' : 'hellbourne';
                        $_team_result[$team_name]['players'][$tmp_hero_stat['nickname']] = $tmp_hero_stat;
                    }
                    $result[$mid] = $_team_result;
                }
            }
        }
        return $result;
    }

    public function playerStats($target = null)
    {
        $result = array();
        if (!empty($target)) {
            $param = $this->buildQuery('player_stats', $target);
            $response = simplexml_load_string($this->doRequest($param));
            if (!empty($response)) {
                foreach ($response->stats->player_stats as $player) {
                    $_result = array(
                        'ranked' => array(),
                        'public' => array(),
                        'casual' => array()
                    );
                    $_result['aid'] = (string)$player->attributes()->aid;
                    foreach ($player->stat as $stat ) {
                        $key = (string)$stat->attributes()->name;
                        $_key = strtolower(preg_replace('/^[a-z]{2,3}_/', '', $key));
                        $val = (string)$stat;
                        if (preg_match('/^rnk_/', $key)) { //ranked
                            $_result['ranked'][$_key] = $val;
                        } else if (preg_match('/^cs_/', $key)) { //casual
                            $_result['casual'][$_key] = $val;
                        } else { //public or global value
                            if ($key == 'nickname') {
                                $_result['nickname'] = $val;
                            } else {
                                $_result['public'][$_key] = $val;
                            }
                        }
                    }
                    $result[] = $_result;
                }
            }
        }
        return $result;
    }

    /**
     * not official api.
     * utility function
     */
    public function heroInfoTable($lang = 'en')
    {
        $result = array();
        if (is_string($lang)) {
            $stringTable = $this->stringTable($lang);
            foreach ($stringTable->stringtables->trans->stat as $item) {
                $_key = (string)$item->attributes()->name;
                if (preg_match('/^Hero_.+/', $_key)) {
                    $tmp  = split('_', $_key);
                    $key  = 'Hero_' . $tmp[1];
                    if (!isset($result[$key])) {
                        $result[$key] = array();
                    }
                    $result[$key][$tmp[2]] = (string)$item;
                }
            }
        }
        return $result;
    }

    public function stringTable($target)
    {
        $response = null;
        if (!empty($target)) {
            $param = $this->buildQuery('stringtable', $target);
            $response = simplexml_load_string($this->doRequest($param));
        }
        return $response;
    }

    protected function doRequest($param)
    {
        if ($this->debug) {
            var_dump(array('doRequest start param[' . $param . ']'));
        }
        $this->lastRequest = clone $this->request;
        $this->lastRequest->setUrl($this->apiBase . '?' . $param);
        try {
            $this->lastResponse = $this->lastRequest->send();
            if ($this->lastResponse->getStatus() != 200) {
                throw new Exception(
                    'invalid server response [' .
                    $this->lastResponse->getStatus() .
                    ']'
                );
            }
            if ($this->debug) {
                var_dump(
                    'doRequest getBody[' .
                    $this->lastResponse->getBody() .
                    ']'
                );
            }

            return $this->lastResponse->getBody();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return null;
    }
}
?>
