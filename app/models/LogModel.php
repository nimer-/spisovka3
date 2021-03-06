<?php

namespace Spisovka;

class LogModel
{

    protected $tb_logaccess = 'log_access';
    protected $tb_logdokument = 'log_dokument';
    protected $tb_logspis = 'log_spis';

    const DOK_UNDEFINED = 00;
    const DOK_NOVY = 11;
    const DOK_ZMENEN = 12;
    const DOK_SMAZAN = 13;
    const DOK_PREDAN = 14;
    const DOK_PRIJAT = 15;
    const DOK_KVYRIZENI = 16;
    const DOK_VYRIZEN = 17;
    const DOK_PRIJATEP = 18;
    const DOK_PREDODESLAN = 20;
    const DOK_ODESLAN = 19;
    const DOK_NEODESLAN = 107;
    const DOK_SPUSTEN = 101;
    const DOK_KESKARTACI = 102;
    const DOK_SKARTOVAN = 103;
    const DOK_ARCHIVOVAN = 104;
    const DOK_SPISOVNA_PREDAN = 105;
    const DOK_SPISOVNA_PRIPOJEN = 106;
    const DOK_PREDANI_ZRUSENO = 108;
    const DOK_PREVZETI_ODMITNUTO = 109;
    const DOK_SPISOVNA_VRACEN = 110;
    const DOK_ZNOVU_OTEVREN = 111;
    const SUBJEKT_VYTVOREN = 21;
    const SUBJEKT_ZMENEN = 22;
    const SUBJEKT_SMAZAN = 23;
    const SUBJEKT_PRIDAN = 24;
    const SUBJEKT_ODEBRAN = 25;
    const PRILOHA_VYTVORENA = 31;
    const PRILOHA_ZMENENA = 32;
    const PRILOHA_SMAZANA = 33;
    const PRILOHA_PRIDANA = 34;
    const PRILOHA_ODEBRANA = 35;
    const SPIS_VYTVOREN = 41;
    const SPIS_ZMENEN = 42;
    const SPIS_SMAZAN = 43;
    const SPIS_DOK_PRIPOJEN = 44;
    const SPIS_DOK_ODEBRAN = 45;
    const SPIS_OTEVREN = 46;
    const SPIS_UZAVREN = 47;
    const SPIS_CHYBA = 48;
    const SPIS_PREDAN = 49;
    const SPIS_PRIJAT = 50;
    const ZAPUJCKA_VYTVORENA = 51;
    const ZAPUJCKA_SCHVALENA = 52;
    const ZAPUJCKA_PRIDELENA = 53;
    const ZAPUJCKA_VRACENA = 54;
    const ZAPUJCKA_ODMITNUTA = 55;

    protected static $typy = array(
        '00' => 'Nedefinovaná činnost',
        '11' => 'Vytvořen nový dokument',
        '12' => 'Dokument změněn',
        '13' => 'Dokument smazán',
        '14' => 'Dokument předán',
        '15' => 'Dokument převzat',
        '16' => 'Dokument označen k vyřízení',
        '17' => 'Dokument vyřízen',
        '18' => 'Dokument přijat e-podatelnou',
        '19' => 'Dokument odeslán',
        '20' => 'Dokument předán k odeslání',
        '101' => 'Spuštěna událost',
        '102' => 'Dokument připraven ke skartaci',
        '103' => 'Dokument skartován',
        '104' => 'Dokument archivován',
        '105' => 'Dokument předán do spisovny',
        '106' => 'Dokument umístěn ve spisovně',
        '107' => 'Dokument nebyl odeslán',
        '108' => 'Předání zrušeno',
        '109' => 'Převzetí odmítnuto',
        self::DOK_SPISOVNA_VRACEN => 'Dokument vrácen ze spisovny',
        self::DOK_ZNOVU_OTEVREN => 'Dokument znovu otevřen',
        '21' => 'Vytvořen nový subjekt',
        '22' => 'Subjekt změněn',
        '23' => 'Subjekt smazán',
        '24' => 'Subjekt přidán k dokumentu',
        '25' => 'Subjekt odebrán z dokumentu',
        '31' => 'Vytvořena nová příloha',
        '32' => 'Příloha změněna',
        '33' => 'Příloha smazána',
        '34' => 'Příloha připojena k dokumentu',
        '35' => 'Příloha odebrána z dokumentu',
        '41' => 'Vytvořen nový spis',
        '42' => 'Spis změněn',
        '43' => 'Spis smazán',
        '44' => 'Dokument připojen ke spisu',
        '45' => 'Dokument odebrán ze spisu',
        '46' => 'Spis otevřen',
        '47' => 'Spis uzavřen',
        '48' => 'Chyba při práci se spisem',
        '49' => 'Spis předán',
        '50' => 'Spis přijat',
        '51' => 'Zápůjčka vytvořena',
        '52' => 'Zápůjčka schválena',
        '53' => 'Dokument zapůjčen',
        '54' => 'Zapůjčený dokument vrácen',
        '55' => 'Zápůjčka odmítnuta'
    );

    /**
     * @param int $document_id
     * @param int $event
     * @param string $poznamka
     * @return int
     */
    public function logDocument($document_id, $event, $poznamka = null)
    {
        $row = array();
        $row['dokument_id'] = (int) $document_id;
        $row['typ'] = $event;
        $row['poznamka'] = $poznamka;

        $row['user_id'] = BaseModel::getUser()->id;
        $row['date'] = new \DateTime();

        return dibi::insert($this->tb_logdokument, $row)
                        ->execute(dibi::IDENTIFIER);
    }

    public function getDocumentsHistory($document_id, $show_all = true)
    {
        $limit = $show_all ? 500 : 5;
        $res = dibi::query(
                        'SELECT * FROM %n ld', $this->tb_logdokument,
                        'WHERE ld.dokument_id = %i', $document_id,
                        'ORDER BY ld.date DESC, ld.id DESC LIMIT %i', $limit
        );
        $rows = $res->fetchAll();
        return array_reverse($rows);
    }

    /**
     * @param int $user_id
     * @param int $type
     * @param boolean $descending
     * @param int $from  primary key
     * @return array
     */
    public function getUsersHistory($user_id, $type, $descending, $from = null, $date_from = null, $date_to
    = null)
    {
        $args = ["SELECT l.*, d.[cislo_jednaci], d.[nazev], d.[popis] FROM %n l", $this->tb_logdokument,
            "JOIN [dokument] d ON d.[id] = l.[dokument_id] WHERE [user_id] = %i AND [typ] = %i",
            $user_id, $type];

        if ($from) {
            $comparison = $descending ? '<=' : '>=';
            array_push($args, "AND l.[id] $comparison %i", $from);
        }

        if ($date_from)
            array_push($args, "AND l.[date] >= %d", $date_from);
        if ($date_to)
            array_push($args, "AND l.[date] <= %d", $date_to);

        $args[] = "ORDER BY l.[id] " . ($descending ? 'DESC' : 'ASC');
        
        $client_config = GlobalVariables::get('client_config');
        $limit = 2 * $client_config->nastaveni->pocet_polozek;
        $args[] = "LIMIT $limit";

        $res = dibi::query($args);
        return $res->fetchAll();
    }

    /**
     * Logovani aktivity u spisu. Nedokoncene, v aplikaci nepouzito.
     * @param int $spis_id
     * @param int $typ
     * @param string $poznamka
     */
    public function logSpis($spis_id, $typ, $poznamka = "")
    {
        $row = array();
        $row['spis_id'] = (int) $spis_id;
        $row['typ'] = $typ;
        $row['poznamka'] = $poznamka;

        $user = BaseModel::getUser();
        $row['user_id'] = $user->id;
        $row['date'] = new \DateTime();

        dibi::insert($this->tb_logspis, $row)->execute();
    }

    /**
     * @param boolean $success Successful login / invalid login attempt (invalid password)
     */
    public function logLogin($user_id, $success, $ip_address)
    {
        $row = array();
        $row['user_id'] = $user_id;
        $row['date'] = new \DateTime();
        $row['ip'] = $ip_address;
        $user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT');
        $row['user_agent'] = substr($user_agent, 0, 190);
        $row['stav'] = $success ? 1 : 0;

        return dibi::insert($this->tb_logaccess, $row)
                        ->execute(dibi::IDENTIFIER);
    }

    public function getLoginHistory($limit = 50, $offset = 0, $user_id = null)
    {
        $res = dibi::query(
                        'SELECT * FROM %n la', $this->tb_logaccess, 'LEFT JOIN [user]',
                        ' u ON (u.id = la.user_id)', '%if', !is_null($user_id), 'WHERE %and',
                        !is_null($user_id) ? array('la.user_id = %i', $user_id) : array(),
                        '%end', 'ORDER BY la.id DESC'
        );
        return $res->fetchAll($offset, $limit);
    }

    public static function eventToString($event)
    {
        return isset(self::$typy[$event]) ? self::$typy[$event] : "";
    }

}
