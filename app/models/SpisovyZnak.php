<?php

namespace Spisovka;

class SpisovyZnak extends TreeModel
{

    protected $name = 'spisovy_znak';
    protected $tb_spoudalost = 'spousteci_udalost';

    /**
     * @return DibiResult
     */
    public function seznam($args = null)
    {
        $params = null;
        if (!empty($args['where'])) {
            $params['where'] = $args['where'];
        } else {
            //$params['where'] = array(array('stav=1'));
        }

        return $this->nacti($params);
    }

    /* public function seznamNativ($args = null, $select = 0)
    {
        $sql = array(
            'from' => array($this->name => 'tb'),
            'cols' => array('*'),
        );
        //$sql['order_sql'] = "LENGTH(tb.nazev), tb.nazev";
        //$sql['order'] = array('tb.nazev');

        if (isset($args['where'])) {
            if (isset($sql['where'])) {
                $sql['where'] = array_merge($sql['where'], $args['where']);
            } else {
                $sql['where'] = $args['where'];
            }
        }

        $result = $this->selectComplex($sql);
        if ($select > 0) {
            return $result;
        } else {
            $rows = $result->fetchAll();
            return ($rows) ? $rows : NULL;
        }
    }
    */
    
    public function ma_podrizene_spisove_znaky($id)
    {
        $sql = array(
            'from' => array($this->name => 'tb'),
            'cols' => array('id'),
            'where' => array(array("parent_id=%i", $id)),
        );

        $result = $this->selectComplex($sql);
        $rows = $result->fetchAll();
        return $rows != false;
    }

    public function getInfo($spisznak_id)
    {

        $sql = array(
            'from' => array($this->name => 'sz'),
            'cols' => array('*'),
            'leftJoin' => array(
                'spousteci_udalost' => array(
                    'from' => array($this->tb_spousteci_udalost => 'udalost'),
                    'on' => array('udalost.id=sz.spousteci_udalost_id'),
                    'cols' => array('nazev' => 'spousteci_udalost_nazev', 'stav' => 'spousteci_udalost_stav', 'poznamka_k_datumu' => 'spousteci_udalost_dtext')
                ),
            ),
            'where' => array(array('sz.id=%i', $spisznak_id))
        );

        $result = $this->selectComplex($sql);
        $row = $result->fetch();
        if ($row)
            return $row;

        throw new \InvalidArgumentException("Spisový znak id '$spisznak_id' neexistuje.");
    }

    public function vytvorit($data)
    {
        $data['stav'] = 1;
        $user_id = self::getUser()->id;
        $data['date_created'] = new \DateTime();
        $data['user_created'] = $user_id;
        $data['date_modified'] = new \DateTime();
        $data['user_modified'] = $user_id;

        if (empty($data['parent_id']))
            $data['parent_id'] = null;

        if ($data['skartacni_lhuta'] === '')
            $data['skartacni_lhuta'] = null;
        if (!isset($data['spousteci_udalost_id']))
            $data['spousteci_udalost_id'] = 3;

        $spisznak_id = $this->vlozitH($data);
        return $spisznak_id;
    }

    public function upravit($data, $spisznak_id)
    {
        $data['date_modified'] = new \DateTime();
        $data['user_modified'] = (int) self::getUser()->id;

        if (empty($data['spousteci_udalost_id']))
            $data['spousteci_udalost_id'] = 3;
        if (!isset($data['parent_id']))
            $data['parent_id'] = null;
        if (empty($data['parent_id']))
            $data['parent_id'] = null;

        if ($data['skartacni_lhuta'] === '')
            $data['skartacni_lhuta'] = null;
        
        if (!empty($data['spousteci_udalost_id']))
            $data['spousteci_udalost_id'] = (int) $data['spousteci_udalost_id'];
        if (!empty($data['stav']))
            $data['stav'] = (int) $data['stav'];

        $this->upravitH($data, $spisznak_id);
    }
    
    /**
     * Vygeneruje pole sekvence_string pro jednu úroveň stromu.
     * @param string $string
     * @param int $id
     * @return string 
     */
    protected function generateSekvenceString($string, $id)
    {
        $parts = explode(".", $string);
        if (count($parts) > 0) {
            foreach ($parts as $pi => $pn) {
                if (is_numeric($pn))
                    $parts[$pi] = sprintf("%04d", intval($pn));
            }
        }
        
        return implode(".", $parts);
    }

    public function odstranit($spisznak_id, $odebrat_strom)
    {
        return $this->odstranitH($spisznak_id, $odebrat_strom);
    }

    /* Hodnoty parametru select:
      0 - vrat seznam pro pouziti v select boxu
      1 - vrat seznam pro pouziti v select boxu s polozkou "Zadna"
      3 - to same, ale pro select box pro vyhledavani
      8 - vrat objekt DibiRow spousteci udalosti (pouzito ve tride Dokument)
      10 - vrat nazev spousteci udalosti dane parametrem kod
     */

    public static function spousteci_udalost($kod = null, $select = 0)
    {
        $result = DbCache::get('s3_Spousteci_udalost');

        if ($result === null) {
            $tb_spoudalost = ':PREFIX:spousteci_udalost';
            $result = dibi::query('SELECT * FROM %n', $tb_spoudalost, 'WHERE stav<>0')->fetchAssoc('id');

            DbCache::set('s3_Spousteci_udalost', $result);
        }

        if ($select == 8)
            return isset($result[$kod]) ? $result[$kod] : null;

        if ($select == 10)
            if ($kod === null)
                return '';
            else
                return isset($result[$kod]) ? $result[$kod]->nazev : '';

        if ($select == 1) {
            $tmp = array();
            // Viz Task #166
            // $tmp[''] = 'Žádná';
            foreach ($result as $dt) {
                $tmp[$dt->id] = \Nette\Utils\Strings::truncate($dt->nazev, 90);
            }
            return $tmp;
        }
        if ($select == 3) {
            $tmp = array();
            $tmp[''] = 'všechny spouštěcí události';
            foreach ($result as $dt) {
                $tmp[$dt->nazev] = \Nette\Utils\Strings::truncate($dt->nazev, 90);
            }
            return $tmp;
        }

        // Pri jakekoli jine hodnote parametru $select vrat prosty seznam vsech udalosti
        return $result;
    }

    public static function stav($stav = null)
    {

        $stav_array = array('1' => 'aktivní',
            '0' => 'neaktivní'
        );

        if (is_null($stav)) {
            return $stav_array;
        } else {
            return isset($stav_array[$stav]) ? $stav_array[$stav] : null;
        }
    }

}
