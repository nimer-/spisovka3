<?php

class DokumentOdeslani extends BaseModel
{

    protected $name = 'dokument_odeslani';

    public function odeslaneZpravy($dokument_id) {

        $sql = array(
            'distinct'=>null,
            'from' => array($this->name => 'ds'),
            'cols' => array('dokument_id','subjekt_id','datum_odeslani','zpusob_odeslani_id','user_id','zprava','cena','hmotnost','cislo_faxu','ds.stav%sql'=>'stav_odeslani','druh_zasilky'),
            'leftJoin' => array(
                'subjekt' => array(
                    'from' => array($this->tb_subjekt => 's'),
                    'on' => array('s.id=ds.subjekt_id'),
                    'cols' => array('*')
                 ),
                'zpusob_odeslani' => array(
                    'from' => array($this->tb_zpusob_odeslani => 'odes'),
                    'on' => array('odes.id=ds.zpusob_odeslani_id'),
                    'cols' => array('nazev'=>'zpusob_odeslani_nazev')
                 ),
            ),
            'order_by' => array('ds.datum_odeslani','s.nazev_subjektu','s.prijmeni','s.jmeno')
        );


        if ( is_array($dokument_id) ) {
            $sql['where'] = array( array('dokument_id IN (%in)', $dokument_id) );
        } else {
            $sql['where'] = array( array('dokument_id=%i',$dokument_id) );
        }
        
        $subjekty = array();
        $result = $this->fetchAllComplet($sql)->fetchAll();
        if ( count($result)>0 ) {
            foreach ($result as $subjekt_index => $subjekt) {
                $subjekty[ $subjekt->dokument_id ][ $subjekt_index ] = $subjekt;
                $subjekty[ $subjekt->dokument_id ][ $subjekt_index ]->druh_zasilky = unserialize($subjekty[ $subjekt->dokument_id ][ $subjekt_index ]->druh_zasilky);
            }

            if ( !is_array($dokument_id) ) {
                return $subjekty[ $dokument_id ];
            } else {
                return $subjekty;
            }
        } else {
            return null;
        }

    }

    public function get($id) {

        $sql = array(
            'distinct'=>false,
            'from' => array($this->name => 'ds'),
            'cols' => array('dokument_id','ds.id'=>'dokodes_id','subjekt_id','datum_odeslani','zpusob_odeslani_id','user_id','zprava','cena','hmotnost','cislo_faxu','ds.stav%sql'=>'stav_odeslani','druh_zasilky'),
            'leftJoin' => array(
                'subjekt' => array(
                    'from' => array($this->tb_subjekt => 's'),
                    'on' => array('s.id=ds.subjekt_id'),
                    'cols' => array('*')
                 ),
                'zpusob_odeslani' => array(
                    'from' => array($this->tb_zpusob_odeslani => 'odes'),
                    'on' => array('odes.id=ds.zpusob_odeslani_id'),
                    'cols' => array('nazev'=>'zpusob_odeslani_nazev')
                 ),
                'dokument' => array(
                    'from' => array($this->tb_dokument => 'dok'),
                    'on' => array('dok.id=ds.dokument_id'),
                    'cols' => array('nazev'=>'dok_nazev','jid'=>'dok_jid','cislo_jednaci'=>'dok_cislo_jednaci','poradi'=>'dok_poradi')
                 ),                
            ),
            'order_by' => array('ds.datum_odeslani','s.nazev_subjektu','s.prijmeni','s.jmeno')
        );


        $sql['where'] = array( array('ds.id=%i',$id) );
        
        $result = $this->fetchAllComplet($sql)->fetch();
        if ( $result ) {
            $result->druh_zasilky = unserialize($result->druh_zasilky);
            return $result;
        } else {
            return null;
        }

    }
    
    
    public function kOdeslani($pouze_posta = null) {

        $sql = array(
            'distinct'=>true,
            'from' => array($this->name => 'ds'),
            'cols' => array('dokument_id','ds.id'=>'dokodes_id','subjekt_id','datum_odeslani','zpusob_odeslani_id','user_id','zprava','cena','hmotnost','cislo_faxu','stav','druh_zasilky'),
            'leftJoin' => array(
                'subjekt' => array(
                    'from' => array($this->tb_subjekt => 's'),
                    'on' => array('s.id=ds.subjekt_id'),
                    'cols' => array('*')
                 ),
                'zpusob_odeslani' => array(
                    'from' => array($this->tb_zpusob_odeslani => 'odes'),
                    'on' => array('odes.id=ds.zpusob_odeslani_id'),
                    'cols' => array('nazev'=>'zpusob_odeslani_nazev')
                 ),
                'dokument' => array(
                    'from' => array($this->tb_dokument => 'dok'),
                    'on' => array('dok.id=ds.dokument_id'),
                    'cols' => array('nazev'=>'dok_nazev','jid'=>'dok_jid','cislo_jednaci'=>'dok_cislo_jednaci','poradi'=>'dok_poradi')
                 ),                
            ),
            'order_by' => array('ds.datum_odeslani','s.nazev_subjektu','s.prijmeni','s.jmeno')
        );


        $sql['where'] = array( array('ds.stav=1') );
        
        if ( !is_null($pouze_posta) ) {
            $sql['where'][] = array('ds.zpusob_odeslani_id=3');
        }
        
        return $this->fetchAllComplet($sql);
        
        $dokumenty = array();
        $result = $this->fetchAllComplet($sql)->fetchAll();
        
        
        if ( count($result)>0 ) {
            foreach ($result as $subjekt_index => $subjekt) {
                $dokumenty[ $subjekt_index ] = $subjekt;
                $dokumenty[ $subjekt_index ]->druh_zasilky = unserialize($dokumenty[ $subjekt_index ]->druh_zasilky);
            }
            return $dokumenty;
        } else {
            return null;
        }

    }
    
    public function getDokumentID($id_dok_odes)
    {
        
        $row = $this->fetchRow(array('id=%i',$id_dok_odes))->fetch();
        if ( $row ) {
            return $row->dokument_id;
        }
        return null;
        
    }
    
    public function odeslano( $id ) {

        if ( empty($id) ) {
            return null;
        }

        $row = array();
        $row['stav'] = 2;
        $row['datum_odeslani'] = new DateTime();
        
        $info = $this->get($id);
        if ( $info ) {
            $Log = new LogModel();
            $Log->logDokument($info->dokument_id, LogModel::DOK_ODESLAN,"Dokument odeslán ". $info->zpusob_odeslani_nazev);
        }

        return $this->update($row, array( array('id=%i',$id) ));

    }    
    
    public function ulozit( $row ) {

        if ( !is_array($row) ) {
            return null;
        }

        //$row = array();
        //$row['dokument_id'] = $dokument_id;
        //$row['subjekt_id'] = $subjekt_id;
        //$row['zpusob_odeslani_id'] = $typ;
        //$row['epodatelna_id'] = $typ;
        //$row['datum_odeslani'] = $typ;
        if ( empty($row['zpusob_odeslani_id']) ) $row['zpusob_odeslani_id'] = null;
        if ( empty($row['epodatelna_id']) ) $row['epodatelna_id'] = null;
        $row['user_id'] = Environment::getUser()->getIdentity()->id;
        $row['date_created'] = new DateTime();


        return $this->insert($row);

    }

}
