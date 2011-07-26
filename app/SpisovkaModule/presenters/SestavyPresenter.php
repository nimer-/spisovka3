<?php

class Spisovka_SestavyPresenter extends BasePresenter
{


    public function renderDefault()
    {

        $user_config = Environment::getVariable('user_config');
        $Sestava = new Sestava();

        // Pevne sestavy
        $result1 = $Sestava->seznam(array('where'=>array('typ=2')));
        $seznam_pevne = $result1->fetchAll();

        $this->template->sestavy_pevne = $seznam_pevne;

        // Volitelne sestavy
        $result2 = $Sestava->seznam(array( 'where'=>array('typ=1') ));
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;
        $paginator->itemCount = count($result2);
        $seznam_volit = $result2->fetchAll($paginator->offset, $paginator->itemsPerPage);
        $this->template->sestavy_volitelne = $seznam_volit;


    }

    public function actionPdf()
    {
        @ini_set("memory_limit",PDF_MEMORY_LIMIT);
        $sestava_id = $this->getParam('id',null);
        $this->forward('detail', array('view'=>'pdf','id'=>$sestava_id));
    }

    public function actionTisk()
    {
        $sestava_id = $this->getParam('id',null);
        $this->forward('detail', array('view'=>'tisk','id'=>$sestava_id));
    }

    public function renderDetail($view)
    {

        $Dokument = new Dokument();
        $Sestava = new Sestava();

        $sestava_id = $this->getParam('id',null);

        $sestava = $Sestava->getInfo($sestava_id);
        $this->template->Sestava = $sestava;

        // info
        $this->template->rok = date('Y');
        $this->template->view = $view;


        // sloupce
        $sloupce_nazvy = array(
                'cislo_jednaci'=>'číslo jednací',
                'spis'=>'Spisová značka',
                'datum_vzniku'=>'Datum doruč./vzniku',
                'subjekty'=>'Odesílatel / adresát',
                'cislo_jednaci_odesilatele'=>'č.j. odesílatele',
                'pocet_listu'=>'Počet listů',
                'pocet_priloh'=>'Počet příloh',
                'pocet_nelistu'=>'Počet nelistů',
                'nazev'=>'Věc',
                'vyridil'=>'Vyřídil',
                'zpusob_vyrizeni'=>'Způsob vyřízení',
                'datum_odeslani'=>'Datum odeslání',
                'spisovy_znak'=>'Spis. znak',
                'skartacni_znak'=>'Skart. znak',
                'skartacni_lhuta'=>'Skart. lhůta',
                'zaznam_vyrazeni'=>'Záznam vyřazení',
            );
        $this->template->sloupce_nazvy = $sloupce_nazvy;


        if ( empty($sestava->sloupce) ) {
            
            $sloupce = array(
                '0'=>'cislo_jednaci',
                '1'=>'spis',
                '2'=>'datum_vzniku',
                '3'=>'subjekty',
                '4'=>'cislo_jednaci_odesilatele',
                '5'=>'pocet_listu',
                '6'=>'pocet_priloh',
                '7'=>'pocet_nelistu',
                '8'=>'nazev',
                '9'=>'vyridil',
                '10'=>'zpusob_vyrizeni',
                '11'=>'datum_odeslani',
                '12'=>'spisovy_znak',
                '13'=>'skartacni_znak',
                '14'=>'skartacni_lhuta',
                '15'=>'zaznam_vyrazeni',
            );
            
        } else {
            $sloupce = unserialize($sestava->sloupce);
        }
        $this->template->sloupce = $sloupce;

        if ( empty( $sestava->parametry ) ) {
            $args = null;
        } else {
            $args = $Dokument->filtr(null,unserialize($sestava->parametry));
        }

        if ( !isset($args['order']) ) {
            $args['order'] = array('d.podaci_denik_poradi','d.nazev');
        }

        // vstup
        $pc_od = $this->getParam('pc_od',null);
        $pc_do = $this->getParam('pc_do',null);
        
        if ( $this->getParam('d_od',null) ) {
            try {
                $d_od = date("Y-m-d", strtotime($this->getParam('d_od',null)));
                //$d_od = new DateTime($this->getParam('d_od',null));
            } catch (Exception $e) {
                $d_od = null;
            }
        }
        if ( $this->getParam('d_do',null) ) {
            try {
                $d_do = date("Y-m-d", strtotime($this->getParam('d_do',null)));
                //$d_do = new DateTime($this->getParam('d_do',null));
            } catch (Exception $e) {
                $d_do = null;
            }
        }
        $rok   = $this->getParam('rok',null);

        // rok
        if ( !empty($rok) ) {
            $args['where'][] = array('d.podaci_denik_rok = %i',$rok);
        }

        // rozsah poradoveho cisla
        if ( !empty($pc_od) && !empty($pc_do) ) {
            $args['where'][] = array(
                                'd.podaci_denik_poradi >= %i AND ',$pc_od,
                                'd.podaci_denik_poradi <= %i',$pc_do
                               );
        } else if ( !empty($pc_od) && empty($pc_do) ) {
            $args['where'][] = array('d.podaci_denik_poradi >= %i',$pc_od);
        } else if ( empty($pc_od) && !empty($pc_do) ) {
            $args['where'][] = array('d.podaci_denik_poradi <= %i',$pc_do);
        }

        // rozsah datumu
        if ( !empty($d_od) && !empty($d_do) ) {
            $args['where'][] = array(
                                'd.datum_vzniku >= %s AND ',$d_od,
                                'd.datum_vzniku <= %s',$d_do
                               );
        } else if ( !empty($d_od) && empty($d_do) ) {
            $args['where'][] = array('d.datum_vzniku >= %s',$d_od);
        } else if ( empty($d_od) && !empty($d_do) ) {
            $args['where'][] = array('d.datum_vzniku <= %s',$d_do);
        }

        // vystup
        //$user_config = Environment::getVariable('user_config');
        //$vp = new VisualPaginator($this, 'vp');
        //$paginator = $vp->getPaginator();
        //$paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;
        $result = $Dokument->seznam($args);
        //$paginator->itemCount = count($result);
        //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
        $seznam = $result->fetchAll();

        if ( count($seznam)>0 ) {

            $pokracovat = $this->getParam('pokracovat',null);

            if ( count($seznam) > 200 && !$pokracovat ) {

                $this->template->prilis_mnoho = 1;
                $this->template->pocet_dokumentu = count($seznam);
                $seznam = array();
                
                $reload_url = Environment::getHttpRequest()->getOriginalUri()->getAbsoluteUri();
                if ( strpos($reload_url,'?') !== false ) {
                    $reload_url .= "&pokracovat=1";
                } else {
                    $reload_url .= "?pokracovat=1";
                }
                $this->template->reload_url = $reload_url;

            } else {

                $dataplus = array();

                $dokument_ids = array();
                foreach ($seznam as $row) {
                    $dokument_ids[] = $row->id;
                }

                $DokSubjekty = new DokumentSubjekt();
                $dataplus['subjekty'] = $DokSubjekty->subjekty($dokument_ids);
                $Dokrilohy = new DokumentPrilohy();
                $dataplus['prilohy'] = $Dokrilohy->prilohy($dokument_ids);
                $DokOdeslani = new DokumentOdeslani();
                $dataplus['odeslani'] = array( '0'=> null );//$DokOdeslani->odeslaneZpravy($dokument_ids);

                foreach ($seznam as $index => $row) {
                    $dok = $Dokument->getInfo($row->id,null, $dataplus);
                    $seznam[$index] = $dok;
                }
            }

        } 

        $this->template->seznam = $seznam;
        $this->setLayout('print');
        if ( $view == 'pdf' ) {
            $this->setView('pdf');
        }


    }

    public function renderNova()
    {
        $this->template->newForm = $this['newForm'];
    }

    public function renderUpravit()
    {
        $this->template->upravitForm = $this['upravitForm'];
    }

    public function renderPodaciarch() 
    {
        @ini_set("memory_limit",PDF_MEMORY_LIMIT);
        $this->setLayout('print');
    }
    
    public function renderPodaciarchnew() 
    {
        @ini_set("memory_limit",PDF_MEMORY_LIMIT);
        $this->setLayout('print');
    }    
    
    protected function createComponentNewForm()
    {

        $typ_dokumentu = array();
        $typ_dokumentu = Dokument::typDokumentu(null,3);

        $typ_doruceni = array(
            '0'=>'všechny',
            '1'=>'pouze doručené přes elektronickou podatelnu',
            '2'=>'pouze doručené přes email',
            '3'=>'pouze doručené přes datovou schránkou',
            '4'=>'doručené mimo epodatelnu',
        );

        $typ_select = array();
        $typ_select = Subjekt::typ_subjektu(null,3);

        $stat_select = array();
        $stat_select = Subjekt::stat(null,3);

        $zpusob_doruceni = array();
        $zpusob_doruceni = Dokument::zpusobDoruceni(null, 3);

        $zpusob_odeslani = array();
        $zpusob_odeslani = Dokument::zpusobOdeslani(null, 3);

        $zpusob_vyrizeni = array();
        $zpusob_vyrizeni = Dokument::zpusobVyrizeni(null, 3);

        $spudalost_seznam = array();
        $spudalost_seznam = SpisovyZnak::spousteci_udalost(null, 3);

        $skartacni_znak = array('0'=>'jakýkoli znak','A'=>'A','V'=>'V','S'=>'S');

        $stav_dokumentu = array(
            ''=>'jakýkoli stav',
            '1'=>'nový / rozpracovaný',
            '2'=>'přidělen / předán',
            '3'=>'vyřizuje se',
            '4'=>'vyřízen',
            '5'=>'vyřazen'
            );

        $pridelen = array('0'=>'kdokoli','2'=>'přidělen','1'=>'předán');


        $form = new AppForm();

        $form->addText('sestava_nazev', 'Název sestavy:', 80, 100);
        $form->addTextArea('sestava_popis', 'Popis sestavy:', 80, 3);
        $form->addSelect('sestava_typ', 'Typ sestavy:', array('1'=>'volitelná sestava','2'=>'pevná sestava'));
        $form->addCheckbox('sestava_filtr', 'Filtrovat:');


        $form->addText('nazev', 'Věc:', 80, 100);
        $form->addTextArea('popis', 'Stručný popis:', 80, 3);
        $form->addText('cislo_jednaci', 'Číslo jednací:', 50, 50);
        $form->addText('spisova_znacka', 'Spisová značka:', 50, 50);
        $form->addSelect('dokument_typ_id', 'Typ Dokumentu:', $typ_dokumentu);
        $form->addSelect('typ_doruceni', 'Způsob doručení:', $typ_doruceni);
        $form->addSelect('zpusob_doruceni_id', 'Způsob doručení:', $zpusob_doruceni);
        $form->addText('cislo_jednaci_odesilatele', 'Číslo jednací odesilatele:', 50, 50);
        $form->addDatePicker('datum_vzniku_od', 'Datum doručení/vzniku (od):', 10);
        $form->addText('datum_vzniku_cas_od', 'Čas doručení (od):', 10, 15);
        $form->addDatePicker('datum_vzniku_do', 'Datum doručení/vzniku do:', 10);
        $form->addText('datum_vzniku_cas_do', 'Čas doručení do:', 10, 15);

        $form->addText('pocet_listu', 'Počet listů:', 5, 10);
        $form->addText('pocet_priloh', 'Počet příloh:', 5, 10);
        $form->addSelect('stav_dokumentu', 'Stav dokumentu:', $stav_dokumentu);

        $form->addText('lhuta', 'Lhůta k vyřízení:', 5, 15)
                ->setValue('30');
        $form->addTextArea('poznamka', 'Poznámka:', 80, 4);

        $form->addSelect('zpusob_vyrizeni', 'Způsob vyřízení:', $zpusob_vyrizeni);
        $form->addDatePicker('datum_vyrizeni_od', 'Datum vyřízení od:', 10);
        $form->addText('datum_vyrizeni_cas_od', 'Čas vyřízení od:', 10, 15);
        $form->addDatePicker('datum_vyrizeni_do', 'Datum vyřízení do:', 10);
        $form->addText('datum_vyrizeni_cas_do', 'Čas vyřízení do:', 10, 15);

        $form->addSelect('zpusob_odeslani', 'Způsob odeslání:', $zpusob_odeslani);
        $form->addDatePicker('datum_odeslani_od', 'Datum odeslání (od):', 10);
        $form->addText('datum_odeslani_cas_od', 'Čas odeslání (od):', 10, 15);
        $form->addDatePicker('datum_odeslani_do', 'Datum odeslání do:', 10);
        $form->addText('datum_odeslani_cas_do', 'Čas odeslání do:', 10, 15);

        $form->addText('spisovy_znak_id', 'spisový znak:');
        $form->addTextArea('ulozeni_dokumentu', 'Uložení dokumentu:', 80, 4);
        $form->addTextArea('poznamka_vyrizeni', 'Poznámka k vyřízení:', 80, 4);
        $form->addSelect('skartacni_znak','Skartační znak: ', $skartacni_znak);
        $form->addText('skartacni_lhuta','Skartační lhuta: ', 5, 5);
        $form->addSelect('spousteci_udalost','Spouštěcí událost: ', $spudalost_seznam);
        $form->addText('vyrizeni_pocet_listu', 'Počet listů:', 5, 10);
        $form->addText('vyrizeni_pocet_priloh', 'Počet příloh:', 5, 10);

        $form->addText('prideleno', 'Přiděleno:', 50, 255);
        $form->addText('predano', 'Předáno:', 50, 255);

        $form->addCheckbox('prideleno_osobne', 'Přiděleno na mé jméno');
        $form->addCheckbox('prideleno_na_organizacni_jednotku', 'Přiděleno na mou organizační jednotku');
        $form->addCheckbox('predano_osobne', 'Předáno na mé jméno');
        $form->addCheckbox('predano_na_organizacni_jednotku', 'Předáno na mou organizační jednotku');


        $form->addSelect('subjekt_type', 'Typ subjektu:', $typ_select);
        $form->addText('subjekt_nazev', 'Název subjektu, jméno, IČ:', 50, 255);
        $form->addText('adresa_ulice', 'Ulice / část obce:', 50, 48);
        $form->addText('adresa_mesto', 'Obec:', 50, 48);
        $form->addText('adresa_psc', 'PSČ:', 10, 10);

        $form->addText('subjekt_email', 'Email:', 50, 250);
        $form->addText('subjekt_telefon', 'Telefon:', 50, 150);
        $form->addText('subjekt_isds', 'ID datové schránky:', 10, 50);

        $form->addSubmit('novy', 'Vytvořit')
                 ->onClick[] = array($this, 'vytvoritClicked');
        $form->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');



        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form;
    }

    public function vytvoritClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        // Pro sestavu
        $sestava = array();
        $sestava['nazev'] = $data['sestava_nazev'];
        $sestava['popis'] = $data['sestava_popis'];
        $sestava['typ'] = $data['sestava_typ'];
        $sestava['filtr'] = ($data['sestava_filtr'])?1:0;
        
        unset($data['sestava_nazev'],$data['sestava_popis'],
              $data['sestava_typ'],$data['sestava_filtr']);

        //Debug::dump($data);

        // pro sestaveni sloupce
        $sloupce = '';
        $sestava['sloupce'] = $sloupce;

        // pro sestaveni parametru
        $params = '';
        $params = serialize($data);

        $sestava['parametry'] = $params;

        //Debug::dump($sestava);
        //exit;

        try {

            $Sestava = new Sestava();
            $Sestava->vytvorit($sestava);


            $this->flashMessage('Sestava "'.$sestava['nazev'].'" byla vytvořena.');
            $this->redirect(':Spisovka:Sestavy:default');
        } catch (DibiException $e) {
            $this->flashMessage('Sestavu "'.$sestava['nazev'].'" se nepodařilo vytvořit.','warning');
            $this->flashMessage('CHYBA: '. $e->getMessage(),'warning');
        }

    }

    protected function createComponentUpravitForm()
    {

        $Sestava = new Sestava();
        $sestava_id = $this->getParam('id',null);
        $sestava = $Sestava->getInfo($sestava_id);
        if ( isset($sestava->parametry) ) {
            $params = unserialize($sestava->parametry);
        } else {
            $params = null;
        }

        $typ_dokumentu = array();
        $typ_dokumentu = Dokument::typDokumentu(null,3);

        $typ_doruceni = array(
            '0'=>'všechny',
            '1'=>'pouze doručené přes elektronickou podatelnu',
            '2'=>'pouze doručené přes email',
            '3'=>'pouze doručené přes datovou schránkou',
            '4'=>'doručené mimo epodatelnu',
        );

        $typ_select = array();
        $typ_select = Subjekt::typ_subjektu(null,3);

        $stat_select = array();
        $stat_select = Subjekt::stat(null,3);

        $zpusob_doruceni = array();
        $zpusob_doruceni = Dokument::zpusobDoruceni(null, 3);

        $zpusob_odeslani = array();
        $zpusob_odeslani = Dokument::zpusobOdeslani(null, 3);

        $zpusob_vyrizeni = array();
        $zpusob_vyrizeni = Dokument::zpusobVyrizeni(null, 3);

        $spudalost_seznam = array();
        $spudalost_seznam = SpisovyZnak::spousteci_udalost(null, 3);

        $skartacni_znak = array('0'=>'jakýkoli znak','A'=>'A','V'=>'V','S'=>'S');

        $stav_dokumentu = array(
            ''=>'jakýkoli stav',
            '1'=>'nový / rozpracovaný',
            '2'=>'přidělen / předán',
            '3'=>'vyřizuje se',
            '4'=>'vyřízen',
            '5'=>'vyřazen'
            );

        $pridelen = array('0'=>'kdokoli','2'=>'přidělen','1'=>'předán');


        $form = new AppForm();
        $form->addHidden('id')
                ->setValue(@$sestava->id);
        $form->addText('sestava_nazev', 'Název sestavy:', 80, 100)
                ->setValue(@$sestava->nazev);
        $form->addTextArea('sestava_popis', 'Popis sestavy:', 80, 3)
                ->setValue(@$sestava->popis);
        $form->addSelect('sestava_typ', 'Typ sestavy:', array('1'=>'volitelná sestava','2'=>'pevná sestava'))
                ->setValue(@$sestava->typ);
        $form->addCheckbox('sestava_filtr', 'Filtrovat:')
                ->setValue(@$sestava->filtr);


        $form->addText('nazev', 'Věc:', 80, 100)
                ->setValue(@$params['nazev']);
        $form->addTextArea('popis', 'Stručný popis:', 80, 3)
                ->setValue(@$params['popis']);
        $form->addText('cislo_jednaci', 'Číslo jednací:', 50, 50)
                ->setValue(@$params['cislo_jednaci']);
        $form->addText('spisova_znacka', 'Spisová značka:', 50, 50)
                ->setValue(@$params['spisova_znacka']);
        $form->addSelect('dokument_typ_id', 'Typ Dokumentu:', $typ_dokumentu)
                ->setValue(@$params['dokument_typ_id']);
        $form->addSelect('typ_doruceni', 'Způsob doručení:', $typ_doruceni)
                ->setValue(@$params['typ_doruceni']);
        $form->addSelect('zpusob_doruceni_id', 'Způsob doručení:', $zpusob_doruceni)
                ->setValue(@$params['zpusob_doruceni_id']);
        $form->addText('cislo_jednaci_odesilatele', 'Číslo jednací odesilatele:', 50, 50)
                ->setValue(@$params['cislo_jednaci_odesilatele']);
        $form->addDatePicker('datum_vzniku_od', 'Datum doručení/vzniku (od):', 10)
                ->setValue(@$params['datum_vzniku_od']);
        $form->addText('datum_vzniku_cas_od', 'Čas doručení (od):', 10, 15)
                ->setValue(@$params['datum_vzniku_cas_od']);
        $form->addDatePicker('datum_vzniku_do', 'Datum doručení/vzniku do:', 10)
                ->setValue(@$params['datum_vzniku_do']);
        $form->addText('datum_vzniku_cas_do', 'Čas doručení do:', 10, 15)
                ->setValue(@$params['datum_vzniku_cas_do']);

        $form->addText('pocet_listu', 'Počet listů:', 5, 10)
                ->setValue(@$params['pocet_listu']);
        $form->addText('pocet_priloh', 'Počet příloh:', 5, 10)
                ->setValue(@$params['pocet_priloh']);
        $form->addSelect('stav_dokumentu', 'Stav dokumentu:', $stav_dokumentu)
                ->setValue(@$params['stav_dokumentu']);

        $form->addText('lhuta', 'Lhůta k vyřízení:', 5, 15)
                ->setValue(@$params['lhuta']);
        $form->addTextArea('poznamka', 'Poznámka:', 80, 4)
                ->setValue(@$params['poznamka']);

        $form->addSelect('zpusob_vyrizeni', 'Způsob vyřízení:', $zpusob_vyrizeni)
                ->setValue(@$params['zpusob_vyrizeni']);
        $form->addDatePicker('datum_vyrizeni_od', 'Datum vyřízení od:', 10)
                ->setValue(@$params['datum_vyrizeni_od']);
        $form->addText('datum_vyrizeni_cas_od', 'Čas vyřízení od:', 10, 15)
                ->setValue(@$params['datum_vyrizeni_cas_od']);
        $form->addDatePicker('datum_vyrizeni_do', 'Datum vyřízení do:', 10)
                ->setValue(@$params['datum_vyrizeni_do']);
        $form->addText('datum_vyrizeni_cas_do', 'Čas vyřízení do:', 10, 15)
                ->setValue(@$params['datum_vyrizeni_cas_do']);

        $form->addSelect('zpusob_odeslani', 'Způsob odeslání:', $zpusob_odeslani)
                ->setValue(@$params['zpusob_odeslani']);
        $form->addDatePicker('datum_odeslani_od', 'Datum odeslání (od):', 10)
                ->setValue(@$params['datum_odeslani_od']);
        $form->addText('datum_odeslani_cas_od', 'Čas odeslání (od):', 10, 15)
                ->setValue(@$params['datum_odeslani_cas_od']);
        $form->addDatePicker('datum_odeslani_do', 'Datum odeslání do:', 10)
                ->setValue(@$params['datum_odeslani_do']);
        $form->addText('datum_odeslani_cas_do', 'Čas odeslání do:', 10, 15)
                ->setValue(@$params['datum_odeslani_cas_do']);

        $form->addText('spisovy_znak_id', 'spisový znak:')
                ->setValue(@$params['spisovy_znak_id']);
        $form->addTextArea('ulozeni_dokumentu', 'Uložení dokumentu:', 80, 4)
                ->setValue(@$params['ulozeni_dokumentu']);
        $form->addTextArea('poznamka_vyrizeni', 'Poznámka k vyřízení:', 80, 4)
                ->setValue(@$params['poznamka_vyrizeni']);
        $form->addSelect('skartacni_znak','Skartační znak: ', $skartacni_znak)
                ->setValue(@$params['skartacni_znak']);
        $form->addText('skartacni_lhuta','Skartační lhuta: ', 5, 5)
                ->setValue(@$params['skartacni_lhuta']);
        $form->addSelect('spousteci_udalost','Spouštěcí událost: ', $spudalost_seznam)
                ->setValue(@$params['spousteci_udalost']);
        $form->addText('vyrizeni_pocet_listu', 'Počet listů:', 5, 10)
                ->setValue(@$params['vyrizeni_pocet_listu']);
        $form->addText('vyrizeni_pocet_priloh', 'Počet příloh:', 5, 10)
                ->setValue(@$params['vyrizeni_pocet_priloh']);

        $form->addText('prideleno', 'Přiděleno:', 50, 255);
        $form->addText('predano', 'Předáno:', 50, 255);

        $form->addCheckbox('prideleno_osobne', 'Přiděleno na mé jméno')
                ->setValue(@$params['prideleno_osobne']);
        $form->addCheckbox('prideleno_na_organizacni_jednotku', 'Přiděleno na mou organizační jednotku')
                ->setValue(@$params['prideleno_na_organizacni_jednotku']);
        $form->addCheckbox('predano_osobne', 'Předáno na mé jméno')
                ->setValue(@$params['predano_osobne']);
        $form->addCheckbox('predano_na_organizacni_jednotku', 'Předáno na mou organizační jednotku')
                ->setValue(@$params['predano_na_organizacni_jednotku']);


        $form->addSelect('subjekt_type', 'Typ subjektu:', $typ_select)
                ->setValue(@$params['subjekt_type']);
        $form->addText('subjekt_nazev', 'Název subjektu, jméno, IČ:', 50, 255)
                ->setValue(@$params['subjekt_nazev']);
        $form->addText('adresa_ulice', 'Ulice / část obce:', 50, 48)
                ->setValue(@$params['adresa_ulice']);
        $form->addText('adresa_mesto', 'Obec:', 50, 48)
                ->setValue(@$params['adresa_mesto']);
        $form->addText('adresa_psc', 'PSČ:', 10, 10)
                ->setValue(@$params['adrea_psc']);

        $form->addText('subjekt_email', 'Email:', 50, 250)
                ->setValue(@$params['subjekt_email']);
        $form->addText('subjekt_telefon', 'Telefon:', 50, 150)
                ->setValue(@$params['subjekt_telefon']);
        $form->addText('subjekt_isds', 'ID datové schránky:', 10, 50)
                ->setValue(@$params['subjekt_isds']);


        $form->addSubmit('upravit', 'Upravit')
                 ->onClick[] = array($this, 'upravitClicked');
        $form->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');



        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form;
    }

    public function upravitClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        // Pro sestavu
        $sestava_id = $data['id'];

        $sestava = array();
        $sestava['nazev'] = $data['sestava_nazev'];
        $sestava['popis'] = $data['sestava_popis'];
        $sestava['typ'] = $data['sestava_typ'];
        $sestava['filtr'] = ($data['sestava_filtr'])?1:0;

        unset($data['id'],$data['sestava_nazev'],$data['sestava_popis'],
              $data['sestava_typ'],$data['sestava_filtr']);

        //Debug::dump($data); exit;

        // pro sestaveni sloupce
        $sloupce = '';
        $sestava['sloupce'] = $sloupce;

        // pro sestaveni parametru
        $params = '';
        $params = serialize($data);

        $sestava['parametry'] = $params;

        //Debug::dump($sestava);
        //exit;

        try {

            $Sestava = new Sestava();
            $Sestava->upravit($sestava,$sestava_id);

            $this->flashMessage('Sestava "'.$sestava['nazev'].'" byla upravena.');
            $this->redirect(':Spisovka:Sestavy:default');
        } catch (DibiException $e) {
            $this->flashMessage('Sestavu "'.$sestava['nazev'].'" se nepodařilo vytvořit.','warning');
            $this->flashMessage('CHYBA: '. $e->getMessage(),'warning');
        }

    }


    public function stornoClicked(SubmitButton $button)
    {
        $this->redirect(':Spisovka:Sestavy:default');
    }


}

