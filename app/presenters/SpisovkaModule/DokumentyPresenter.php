<?php

class Spisovka_DokumentyPresenter extends BasePresenter
{

    private $filtr;
    private $filtr_bezvyrizenych;
    private $filtr_moje;
    private $zakaz_filtr = false;
    private $hledat;
    private $seradit;
    private $odpoved = false;
    private $typ_evidence = null;
    private $oddelovac_poradi = null;
    private $pdf_output = 0;
    private $hromadny_tisk = false;
    private $hromadny_tisk_vyber;

    public function startup()
    {
        $client_config = Nette\Environment::getVariable('client_config');
        $this->typ_evidence = 0;
        if (isset($client_config->cislo_jednaci->typ_evidence)) {
            $this->typ_evidence = $client_config->cislo_jednaci->typ_evidence;
        } else {
            $this->typ_evidence = 'priorace';
        }
        if (isset($client_config->cislo_jednaci->oddelovac)) {
            $this->oddelovac_poradi = $client_config->cislo_jednaci->oddelovac;
        } else {
            $this->oddelovac_poradi = '/';
        }
        $this->template->Oddelovac_poradi = $this->oddelovac_poradi;

        parent::startup();
    }

    public function renderDefault()
    {
        $post = $this->getRequest()->getPost();
        if (isset($post['hromadna_submit'])) {
            $this->actionAkce($post);
        }

        $this->template->Typ_evidence = $this->typ_evidence;

        $client_config = Nette\Environment::getVariable('client_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek
                    : 20;

        $Dokument = new Dokument();

        $filtr = UserSettings::get('spisovka_dokumenty_filtr'); 
        if ($filtr) {
            $filtr = unserialize($filtr);
        } else {
            // filtr nezjisten - pouzijeme nejaky
            $filtr = array();
            $filtr['filtr'] = 'pridelene';
            $filtr['bez_vyrizenych'] = false;
            $filtr['jen_moje'] = false;
        }
            
        $args_f = $Dokument->fixedFiltr($filtr['filtr'], $filtr['bez_vyrizenych'],
                $filtr['jen_moje']);
        $this->filtr = $filtr['filtr'];
        $this->filtr_bezvyrizenych = $filtr['bez_vyrizenych'];
        $this->filtr_moje = $filtr['jen_moje'];
        $this->template->no_items = 2; // indikator pri nenalezeni dokumentu po filtraci

        $args_h = array();
        $hledat = UserSettings::get('spisovka_dokumenty_hledat'); 
        if ($hledat)
            $hledat = unserialize($hledat);

        try {
            if (isset($hledat))
                if (is_array($hledat) ) {
                    // podrobne hledani = array
                    $args_h = $Dokument->paramsFiltr($hledat);
                    $this->template->no_items = 4; // indikator pri nenalezeni dokumentu pri pokorčilem hledani
                } else {
                    // rychle hledani = string
                    $args_h = $Dokument->hledat($hledat);
                    $this->hledat = $hledat;
                    $this->template->no_items = 3; // indikator pri nenalezeni dokumentu pri hledani
                }
        } catch (Exception $e) {
            $this->flashMessage($e->getMessage() . " Hledání bylo zrušeno.", 'warning');
            $this->forward(':Spisovka:Vyhledat:reset');
        }
        $this->template->s3_hledat = $hledat;

        /* [P.L.] Pokud uzivatel zvoli pokrocile hledani a hleda dokumenty pridelene/predane uzivateli ci jednotce,
          ignoruj filtr, ktery uzivatel nastavil a pouzij filtr "Vsechny" */
        if (is_array($hledat) && (isset($hledat['prideleno']) || isset($hledat['predano']) || isset($hledat['prideleno_org']) || isset($hledat['predano_org'])
                )) {
            $bez_vyrizenych = false;
            if (isset($filtr['bez_vyrizenych']))
                $bez_vyrizenych = $filtr['bez_vyrizenych'];
            $args_f = $Dokument->fixedFiltr('vse', $bez_vyrizenych, false);
            $this->zakaz_filtr = true;
        }

        if (!$this->hromadny_tisk)
            $args = $Dokument->spojitAgrs(@$args_f, @$args_h);
        else {
            $args = array('where' => array(
                    array('d.[id] IN (%i)', $this->hromadny_tisk_vyber)));
        }

        $seradit = UserSettings::get('spisovka_dokumenty_seradit'); 
        if ($seradit) {
            $Dokument->seradit($args, $seradit);
        }
            
        $this->seradit = $seradit;
        $this->template->s3_seradit = $seradit;
        $this->template->seradit = $seradit;

        $args = $Dokument->spisovka($args);
        $result = $Dokument->seznam($args);
        $paginator->itemCount = count($result);

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk || $this->hromadny_tisk) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();
            $this->setLayout(false);
            $this->setView('print');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 1;
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();
            $this->setLayout(false);
            $this->setView('print');
        } else {
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
        }

        if (count($seznam) > 0) {
            $dokument_ids = array();
            foreach ($seznam as $row) {
                $dokument_ids[] = $row->id;
            }

            $DokSubjekty = new DokumentSubjekt();
            $subjekty = $DokSubjekty->subjekty($dokument_ids);
            $pocty_souboru = DokumentPrilohy::pocet_priloh($dokument_ids);

            foreach ($seznam as $index => $row) {
                $dok = $Dokument->getInfo($row->id, '');
                if (empty($dok->stav_dokumentu)) {
                    // toto má myslím zajistit, aby se v seznamu nezobrazovaly rozepsané dokumenty
                    unset($seznam[$index]);
                    continue;
                }
                $id = $dok->id;
                $dok->subjekty = isset($subjekty[$id]) ? $subjekty[$id] : null;
                // $dok->prilohy = isset($prilohy[$id]) ? $prilohy[$id] : null;
                $dok->pocet_souboru = isset($pocty_souboru[$id]) ? $pocty_souboru[$id] : 0;
                $seznam[$index] = $dok;
            }
        }

        $this->template->seznam = $seznam;

        $this->template->filtrForm = $this['filtrForm'];
        $this->template->seraditForm = $this['seraditForm'];
    }

    protected function shutdown($response)
    {

        if ($this->pdf_output == 1 || $this->pdf_output == 2) {

            ob_start();
            $response->send($this->getHttpRequest(), $this->getHttpResponse());
            $content = ob_get_clean();
            if ($content) {

                @ini_set("memory_limit", PDF_MEMORY_LIMIT);

                if ($this->pdf_output == 2) {
                    $content = str_replace("<td", "<td valign='top'", $content);
                    $content = str_replace("Vytištěno dne:", "Vygenerováno dne:", $content);
                    $content = str_replace("Vytiskl: ", "Vygeneroval: ", $content);
                    $content = preg_replace('#<div id="tisk_podpis">.*?</div>#s', '', $content);
                    $content = preg_replace('#<table id="table_top">.*?</table>#s', '',
                            $content);

                    $mpdf = new mPDF('iso-8859-2', 'A4', 9, 'Helvetica');

                    $app_info = Nette\Environment::getVariable('app_info');
                    $app_info = explode("#", $app_info);
                    $app_name = (isset($app_info[2])) ? $app_info[2] : 'OSS Spisová služba v3';
                    $mpdf->SetCreator($app_name);
                    $mpdf->SetAuthor($this->user->getIdentity()->display_name);
                    $mpdf->SetTitle('Spisová služba - Detail dokumentu');

                    $mpdf->defaultheaderfontsize = 10; /* in pts */
                    $mpdf->defaultheaderfontstyle = 'B'; /* blank, B, I, or BI */
                    $mpdf->defaultheaderline = 1;  /* 1 to include line below header/above footer */
                    $mpdf->defaultfooterfontsize = 9; /* in pts */
                    $mpdf->defaultfooterfontstyle = ''; /* blank, B, I, or BI */
                    $mpdf->defaultfooterline = 1;  /* 1 to include line below header/above footer */
                    $mpdf->SetHeader('||' . $this->template->Urad->nazev);
                    $mpdf->SetFooter("{DATE j.n.Y}/" . $this->user->getIdentity()->display_name . "||{PAGENO}/{nb}"); /* defines footer for Odd and Even Pages - placed at Outer margin */

                    $mpdf->WriteHTML($content);

                    $mpdf->Output('dokument.pdf', 'I');
                } else {
                    $content = str_replace("<td", "<td valign='top'", $content);
                    $content = str_replace("Vytištěno dne:", "Vygenerováno dne:", $content);
                    $content = str_replace("Vytiskl: ", "Vygeneroval: ", $content);
                    $content = preg_replace('#<div id="tisk_podpis">.*?</div>#s', '', $content);
                    $content = preg_replace('#<table id="table_top">.*?</table>#s', '',
                            $content);

                    $mpdf = new mPDF('iso-8859-2', 'A4-L', 9, 'Helvetica');

                    $app_info = Nette\Environment::getVariable('app_info');
                    $app_info = explode("#", $app_info);
                    $app_name = (isset($app_info[2])) ? $app_info[2] : 'OSS Spisová služba v3';
                    $mpdf->SetCreator($app_name);
                    $mpdf->SetAuthor($this->user->getIdentity()->display_name);
                    $mpdf->SetTitle('Spisová služba - Tisk');

                    $mpdf->defaultheaderfontsize = 10; /* in pts */
                    $mpdf->defaultheaderfontstyle = 'B'; /* blank, B, I, or BI */
                    $mpdf->defaultheaderline = 1;  /* 1 to include line below header/above footer */
                    $mpdf->defaultfooterfontsize = 9; /* in pts */
                    $mpdf->defaultfooterfontstyle = ''; /* blank, B, I, or BI */
                    $mpdf->defaultfooterline = 1;  /* 1 to include line below header/above footer */
                    $mpdf->SetHeader('Seznam dokumentů||' . $this->template->Urad->nazev);
                    $mpdf->SetFooter("{DATE j.n.Y}/" . $this->user->getIdentity()->display_name . "||{PAGENO}/{nb}"); /* defines footer for Odd and Even Pages - placed at Outer margin */

                    $mpdf->WriteHTML($content);

                    $mpdf->Output('spisova_sluzba.pdf', 'I');
                }
            }
        }
    }

    public function actionDetail()
    {
        $Dokument = new Dokument();

        // Nacteni parametru
        $dokument_id = $this->getParameter('id', null);
        if ($dokument_id === null)
            $dokument_id = $this->getHttpRequest()->getPost('id');
        
        $dokument = $Dokument->getInfo($dokument_id, "subjekty,soubory,odeslani,workflow");
        if ($dokument) {
            // dokument zobrazime

            if ($dokument->stav === 0) {
                $this->flashMessage("Pokoušel jste se zobrazit dokument, který je rozepsaný. V menu zvolte \"Nový dokument\" a vytvoření dokumentu prosím dokončete.",
                        'warning');
                $this->redirect('default');
            }
            
            if (!empty($dokument->identifikator)) {
                $Epodatelna = new Epodatelna();
                $dokument->identifikator = $Epodatelna->identifikator(unserialize($dokument->identifikator));
            }

            $this->template->Dok = $dokument;

            $user = $this->user;
            $user_id = $user->getIdentity()->id;

            $this->template->Pridelen = 0;
            $this->template->Predan = 0;
            $this->template->AccessEdit = 0;
            $this->template->AccessView = 0;
            $formUpravit = $this->getParameter('upravit', null);

            if (count($dokument->workflow) > 0) {
                // uzivatel na dokumentu nekdy pracoval, tak mu dame moznost aspon nahlizet
                foreach ($dokument->workflow as $wf) {
                    if ($wf->prideleno_id == $user_id) {
                        $this->template->AccessView = 1;
                    }
                }
            }

            // P.L.
            $isVedouci = $user->isAllowed(NULL, 'is_vedouci');
            if ($isVedouci) {
                // Uzivatel muze byt vedoucim jenom jednoho utvaru
                $id = Orgjednotka::dejOrgUzivatele();
                $povoleneOrgJednotky = array();
                if ($id)
                    $povoleneOrgJednotky = Orgjednotka::childOrg($id);

                if (in_array(@$dokument->prideleno->orgjednotka_id, $povoleneOrgJednotky) || in_array(@$dokument->predano->orgjednotka_id,
                                $povoleneOrgJednotky)) {
                    $this->template->AccessView = 1;

                    // vedouci ma pristup k dokumentu, ktery je predan primo na org. jednotku (kdyz chybi konkretni uzivatel)
                    if ($user->isAllowed('Dokument', 'menit_moje_oj') || isset($dokument->predano) && @$dokument->predano->prideleno_id == null)
                        $this->template->AccessEdit = 1;

                    if (in_array(@$dokument->prideleno->orgjednotka_id, $povoleneOrgJednotky))
                        $this->template->Pridelen = 1;
                    else {
                        $this->template->Predan = 1;
                        $this->template->AccessEdit = 0;
                    }
                }
            }

            // Prideleny nebo predany uzivatel
            if (@$dokument->prideleno->prideleno_id == $user_id || (Orgjednotka::isInOrg(@$dokument->prideleno->orgjednotka_id) && $user->isAllowed('Dokument',
                            'menit_moje_oj'))) {
                // prideleny
                $this->template->AccessEdit = 1;
                $this->template->AccessView = 1;
                $this->template->Pridelen = 1;
            } else if (@$dokument->predano->prideleno_id == $user_id || (Orgjednotka::isInOrg(@$dokument->predano->orgjednotka_id) && $user->isAllowed('Dokument',
                            'menit_moje_oj'))) {
                // predany
                $this->template->AccessEdit = 0;
                $this->template->AccessView = 1;
                $this->template->Predan = 1;
            } else if ($user->isAllowed('Dokument', 'cist_moje_oj') && (Orgjednotka::isInOrg(@$dokument->prideleno->orgjednotka_id) || Orgjednotka::isInOrg(@$dokument->predano->orgjednotka_id))) {
                $this->template->AccessView = 1;
            }

            if ($user->isAllowed('Dokument', 'cist_vse'))
                $this->template->AccessView = 1;
            
            // Dokument je vyrizeny nebo v pozdejsim stavu workflow
            if ($dokument->stav_dokumentu >= 4) {
                $this->template->AccessEdit = 0;
            }

            // Dokument je zapujcen
            if ($dokument->stav_dokumentu == 11) {
                $this->template->Pridelen = 0;
                $this->template->AccessEdit = 0;
                $Zapujcka = new Zapujcka();
                $this->template->Zapujcka = $Zapujcka->getDokument($dokument_id);
            } else {
                $this->template->Zapujcka = null;
            }

            // Pokud uzivatel dokument nekomu predal, zakaz docasne praci s dokumentem
            if ($this->template->Pridelen && !empty($dokument->predano))
                $this->template->AccessEdit = 0;
                
            // SuperAdmin - moznost zasahovat do dokumentu
            if (Acl::isInRole('superadmin')) {
                $this->template->AccessEdit = 1;
                $this->template->AccessView = 1;
                $this->template->Pridelen = 1;
            }
            
            $lzePredatVyrizeneDokumenty = Settings::get('spisovka_allow_forward_finished_documents', false);
            $this->template->LzePredatDokument = $this->template->Pridelen
                    && ($dokument->stav_dokumentu <= 3 || $lzePredatVyrizeneDokumenty);
            
            $this->template->isRozdelany = $dokument->stav_dokumentu == 1
                                            || $user->isInRole('superadmin');

            $this->template->FormUpravit = $this->template->AccessEdit ? $formUpravit : null;

            $this->template->FormUdalost = $this->getParameter('udalost', false) && $dokument->stav_dokumentu == 4;

            $SpisovyZnak = new SpisovyZnak();
            $this->template->SpisoveZnaky = $SpisovyZnak->seznam(null);

            $this->template->Typ_evidence = $this->typ_evidence;
            $this->template->SouvisejiciDokumenty = array();
            $this->template->povolitOdpoved = false;
            $souvisejici_dokumenty = array();
            if ($this->typ_evidence == 'priorace') {
                // Nacteni souvisejicicho dokumentu
                $Souvisejici = new SouvisejiciDokument();
                $this->template->SouvisejiciDokumenty = $Souvisejici->souvisejici($dokument_id);
                if (count($this->template->SouvisejiciDokumenty) > 0) {
                    foreach ($this->template->SouvisejiciDokumenty as $souvisejici_dok) {
                        $souvisejici_dokumenty[$souvisejici_dok->id] = $souvisejici_dok->id;
                    }
                }
                if (!empty($dokument->cislo_jednaci) && $dokument->typ_dokumentu->smer == 0) {
                    $this->template->povolitOdpoved = true;
                    $stejne_dokumenty = $Dokument->stejne($dokument->cislo_jednaci, $dokument->id);
                    if (count($stejne_dokumenty) > 0) {
                        // nelze jiz vytvorit odpoved - jedno cislo jednaci muze mit maximalne jen 2 JID
                        $this->template->povolitOdpoved = false;
                    }
                }
            }


            // Kontrola lhuty a skartace
            if ($dokument->lhuta_stav == 2 && $dokument->stav_dokumentu < 4) {
                $this->flashMessage('Vypršela lhůta k vyřízení! Vyřiďte neprodleně tento dokument.',
                        'warning');
            } else if ($dokument->lhuta_stav == 1 && $dokument->stav_dokumentu < 4) {
                $this->flashMessage('Za pár dní vyprší lhůta k vyřízení! Vyřiďte co nejrychleji tento dokument.');
            }

            // Kontrola existence nazvu
            if ($this->template->Pridelen && $this->template->AccessEdit) {
                if ($dokument->stav_dokumentu >= 2 && !Acl::isInRole('podatelna') && (empty($dokument->nazev) || $dokument->nazev == "(bez názvu)" )) {
                    $this->template->nutnyNadpis = 1;
                    $this->template->FormUpravit = 'metadata';
                }
            }


            // Volba vystupu - web/tisk/pdf
            $tisk = $this->getParameter('print');
            $pdf = $this->getParameter('pdfprint');
            if ($tisk) {
                @ini_set("memory_limit", PDF_MEMORY_LIMIT);
                $this->setLayout(false);
                $this->setView('printdetail');
            } elseif ($pdf) {
                @ini_set("memory_limit", PDF_MEMORY_LIMIT);
                $this->pdf_output = 2;
                $this->setLayout(false);
                $this->setView('printdetail');
            }

            $this->invalidateControl('dokspis');

            if (!$this->template->AccessView)
                $this->setView('noaccess');
        } else {
            // dokument neexistuje nebo se nepodarilo nacist
            $this->setView('noexist');
        }
    }

    public function renderDetail()
    {
        $this->template->typy_dokumentu = Dokument::typDokumentu();
    }

    protected function actionAkce($data)
    {
        if (!isset($data['hromadna_akce']) || !isset($data['dokument_vyber']))
            return;

        $Workflow = new Workflow();

        switch ($data['hromadna_akce']) {
            case 'tisk':
                $this->hromadny_tisk = 1;
                $this->hromadny_tisk_vyber = $data['dokument_vyber'];
                break;

            /* Prevzeti vybranych dokumentu */
            case 'prevzit':

                $count_ok = $count_failed = 0;
                foreach ($data['dokument_vyber'] as $dokument_id) {
                    if ($Workflow->predany($dokument_id)) {
                        if ($Workflow->prevzit($dokument_id))
                            $count_ok++;
                        else
                            $count_failed++;
                    }
                }
                if ($count_ok > 0)
                    $this->flashMessage('Úspěšně jste převzal ' . $count_ok . ' dokumentů.');
                if ($count_failed > 0)
                    $this->flashMessage('U ' . $count_failed . ' dokumentů se nepodařilo převzít dokument!',
                            'warning');
                break;

            /* Predani vybranych dokumentu do spisovny  */
            case 'predat_spisovna':

                $count_ok = $count_failed = 0;
                foreach ($data['dokument_vyber'] as $dokument_id) {
                    $stav = $Workflow->predatDoSpisovny($dokument_id, 1);
                    if ($stav === true) {
                        $count_ok++;
                    } else {
                        if (is_string($stav)) {
                            $this->flashMessage($stav, 'warning');
                        }
                        $count_failed++;
                    }
                }
                if ($count_ok > 0) {
                    $this->flashMessage('Úspěšně jste předal ' . $count_ok . ' dokumentů do spisovny.');
                }
                if ($count_failed > 0) {
                    $this->flashMessage($count_failed . ' dokumentů se nepodařilo předat do spisovny!',
                            'warning');
                }
                if ($count_ok > 0 && $count_failed > 0) {
                    $this->redirect('this');
                }
                break;
        }
    }

    public function actionPrevzit()
    {
        $dokument_id = $this->getParameter('id', null);

        $Workflow = new Workflow();
        if ($Workflow->predany($dokument_id)) {
            if ($Workflow->prevzit($dokument_id)) {
                $this->flashMessage('Úspěšně jste si převzal tento dokument.');
            } else {
                $this->flashMessage('Převzetí dokumentu do vlastnictví se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        } else {
            $this->flashMessage('Nemáte oprávnění k převzetí dokumentu.', 'warning');
        }

        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function actionZrusitprevzeti()
    {
        $dokument_id = $this->getParameter('id', null);

        $Workflow = new Workflow();
        if ($Workflow->prirazeny($dokument_id)) {
            if ($Workflow->zrusit_prevzeti($dokument_id)) {
                $this->flashMessage('Zrušil jste předání dokumentu.');
                $Log = new LogModel();
                $Log->logDokument($dokument_id, LogModel::DOK_PREDANI_ZRUSENO,
                        "Předání dokumentu bylo zrušeno.");
            } else {
                $this->flashMessage('Zrušení předání se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        } else {
            $this->flashMessage('Nemáte oprávnění ke zrušení předání dokumentu.', 'warning');
        }
        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function actionOdmitnoutprevzeti()
    {
        $dokument_id = $this->getParameter('id', null);

        $Workflow = new Workflow();
        if ($Workflow->predany($dokument_id)) {
            if ($Workflow->zrusit_prevzeti($dokument_id)) {
                $this->flashMessage('Odmítl jste převzetí dokumentu.');
                $Log = new LogModel();
                $Log->logDokument($dokument_id, LogModel::DOK_PREVZETI_ODMITNUTO,
                        "Uživatel odmítnul převzít dokument.");
                $this->redirect(':Spisovka:Dokumenty:default');
            } else {
                $this->flashMessage('Odmítnutí převzetí se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        } else {
            $this->flashMessage('Nemůžete odmítnout převzetí dokumentu, který Vám nebyl předán.',
                    'warning');
        }

        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function actionKvyrizeni()
    {
        $dokument_id = $this->getParameter('id', null);

        $Workflow = new Workflow();
        if ($Workflow->prirazeny($dokument_id)) {
            if ($Workflow->vyrizuje($dokument_id)) {
                $Workflow->zrusit_prevzeti($dokument_id);

                $DokumentSpis = new DokumentSpis();
                $spisy = $DokumentSpis->spisy($dokument_id);
                if (count($spisy) > 0) {
                    $Dokument = new Dokument();
                    foreach ($spisy as $spis) {
                        $data = array(
                            "spisovy_znak_id" => $spis->spisovy_znak_id,
                            "skartacni_znak" => $spis->skartacni_znak,
                            "skartacni_lhuta" => $spis->skartacni_lhuta,
                            "spousteci_udalost_id" => $spis->spousteci_udalost_id
                        );
                        $Dokument->ulozit($data, $dokument_id);
                        unset($data);
                    }
                }

                $this->flashMessage('Převzal jste tento dokument k vyřízení.');
            } else {
                $this->flashMessage('Označení dokumentu k vyřízení se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        } else {
            $this->flashMessage('Nemáte oprávnění označit dokument k vyřízení.', 'warning');
        }
        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function actionVyrizeno()
    {
        $dokument_id = $this->getParameter('id', null);

        $Workflow = new Workflow();
        if ($Workflow->prirazeny($dokument_id)) {
            $ret = $Workflow->vyrizeno($dokument_id);
            if ($ret === "udalost") {
                // manualni udalost
                $this->flashMessage('Označil jste tento dokument za vyřízený!');
                $this->redirect(':Spisovka:Dokumenty:detail',
                        array('id' => $dokument_id, 'udalost' => 1));
            } else if ($ret === "neprideleno") {
                // neprideleno
                $this->flashMessage('Nemáte oprávnění označit dokument za vyřízený.', 'warning');
            } else if ($ret === true) {
                // automaticka udalost
                $this->flashMessage('Označil jste tento dokument za vyřízený!');
            } else {
                $this->flashMessage('Označení dokumentu za vyřízený se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        } else {
            $this->flashMessage('Nemáte oprávnění označit dokument za vyřízený.', 'warning');
        }
        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function renderCjednaci()
    {
        $this->template->dokument_id = $this->getParameter('id', null);
        $this->template->evidence = $this->getParameter('evidence', 0);
    }

    // tato metoda slouží pouze pro sběrný arch
    public function actionVlozitdosbernehoarchu()
    {
        try {
            if ($this->typ_evidence != 'sberny_arch')
                throw new Exception("operace je platná pouze u typu evidence sběrný arch");

            $dokument_id = (int) $this->getParameter('id', null);
            $iniciacni_dokument_id = (int) $this->getParameter('vlozit_do', null);

            $Dokument = new Dokument();

            // getBasicInfo neháže výjimku, pokud dokument neexistuje
            // nasledujici prikaz pouze overi, ze dokument_id existuje
            $dok1 = $Dokument->getBasicInfo($dokument_id);
            // predpoklad - dok2 je iniciacni dokument spisu
            $dok2 = $Dokument->getBasicInfo($iniciacni_dokument_id);

            if (!($dok1 && $dok2) || $dok2->poradi != 1)
                throw new Exception("neplatný parametr");

            // spojit s dokumentem
            $poradi = $Dokument->getMaxPoradi($dok2->cislo_jednaci_id);

            $cislo_jednaci = $dok2->cislo_jednaci;
            $data = array();
            $data['cislo_jednaci_id'] = $dok2->cislo_jednaci_id;
            $data['cislo_jednaci'] = $cislo_jednaci;
            $data['poradi'] = $poradi;
            $data['podaci_denik'] = $dok2->podaci_denik;
            $data['podaci_denik_poradi'] = $dok2->podaci_denik_poradi;
            $data['podaci_denik_rok'] = $dok2->podaci_denik_rok;

            // predpoklad - spis musi existovat, jinak je neco hodne spatne
            $Spis = new Spis();
            $spis = $Spis->findByName($cislo_jednaci);
            if (!$spis)
                throw new Exception("chyba integrity dat. Spis '$cislo_jednaci' neexistuje.");

            $Dokument->update($data, array(array('id=%i', $dokument_id)));

            // pripojime
            $DokumentSpis = new DokumentSpis();
            $DokumentSpis->pripojit($dokument_id, $spis->id);
            // zaznam do logu az nakonec, kdyz jsou vsechny operace uspesne

            $Log = new LogModel();
            $Log->logDokument($dokument_id, LogModel::DOK_UNDEFINED,
                    'Dokument připojen do evidence. Přiděleno číslo jednací: ' . $cislo_jednaci);

            echo '###zaevidovano###' . $this->link('detail', array('id' => $dokument_id));
        } catch (Exception $e) {
            echo __METHOD__ . "() - " . $e->getMessage();
        }

        $this->terminate();
    }

    public function actionPridelitcj()
    {
        $dokument_id = $this->getParameter('id', null);
        $cjednaci_id = $this->getParameter('cislo_jednaci_id', null);

        $Dokument = new Dokument();
        $dokument_info = $Dokument->getInfo($dokument_id);
        if (empty($dokument_info))
            throw new Exception("Přidělení č.j. - nemohu načíst dokument id $dokument_id.");

        // Je treba zkontrolovat, jestli dokument uz cislo jednaci nema prideleno
        if (!empty($dokument_info['cislo_jednaci_id'])) {
            // throw new Exception("Dokument má již č.j. přiděleno.");
            $this->flashMessage('Dokument má již číslo jednací přiděleno.', 'error');
            $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
        }

        $CJ = new CisloJednaci();

        if (!empty($cjednaci_id)) {
            $cjednaci = $CJ->nacti($cjednaci_id);
            unset($cjednaci_id);
        } else {
            $cjednaci = $CJ->generuj(1);
        }

        $poradi = $Dokument->getMaxPoradi($cjednaci_id);

        $data = array();
        $data['cislo_jednaci_id'] = $cjednaci->id;
        $data['cislo_jednaci'] = $cjednaci->cislo_jednaci;
        $data['poradi'] = $poradi;
        $data['podaci_denik'] = $cjednaci->podaci_denik;
        $data['podaci_denik_poradi'] = $cjednaci->poradove_cislo;
        $data['podaci_denik_rok'] = $cjednaci->rok;

        $dokument = $Dokument->update($data, array(array('id=%i', $dokument_id))); //   array('dokument_id'=>0);// $Dokument->ulozit($data);
        if ($dokument) {

            $this->flashMessage('Dokument připojen do evidence.');

            $Log = new LogModel();
            $Log->logDokument($dokument_id, LogModel::DOK_UNDEFINED,
                    'Dokument připojen do evidence. Přiděleno číslo jednací: ' . $cjednaci->cislo_jednaci);

            if ($this->typ_evidence == 'sberny_arch') {

                $Spis = new Spis();
                $spis = $Spis->findByName($cjednaci->cislo_jednaci);
                if (!$spis) {
                    // vytvorime spis

                    $spis_new = array(
                        'nazev' => $cjednaci->cislo_jednaci,
                        'popis' => "",
                        /* 'spousteci_udalost_id' => 3, */
                        'skartacni_znak' => 'S',
                        'skartacni_lhuta' => '10',
                        'typ' => 'S',
                        'stav' => 1,
                        'parent_id' => 1
                    );
                    $spis_id = $Spis->vytvorit($spis_new);
                    $spis = $Spis->getInfo($spis_id);
                }

                // pripojime
                if ($spis) {
                    $DokumentSpis = new DokumentSpis();
                    $DokumentSpis->pripojit($dokument_id, $spis->id);
                }
            }
        }

        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function renderNovy()
    {

        $Dokumenty = new Dokument();

        $args_rozd = array();
        $args_rozd['where'] = array(
            array('stav=%i', 0),
            array('user_created=%i', $this->user->getIdentity()->id),
        );

        $args_rozd['order'] = array('date_created' => 'DESC');

        $this->template->Typ_evidence = $this->typ_evidence;

        $cisty = $this->getParameter('cisty', 0);
        $spis_id = $this->getParameter('spis_id', null);
        $rozdelany_dokument = null;

        if ($cisty == 1) {
            $Dokumenty->odstranit_rozepsane();
            $this->redirect(':Spisovka:Dokumenty:novy');
            //$rozdelany_dokument = null;
        } else if ($spis_id) {
            $Dokumenty->odstranit_rozepsane();
        } else {
            $rozdelany_dokument = $Dokumenty->seznamKlasicky($args_rozd);
        }

        if (count($rozdelany_dokument) > 0) {
            $dokument = $rozdelany_dokument[0];
            // Oprava Task #254
            $dokument = $Dokumenty->getInfo($dokument->id);

            $this->flashMessage('Byl detekován a načten rozepsaný dokument.<p>Pokud chcete založit úplně nový dokument, klikněte na následující odkaz. <a href="' . $this->link(':Spisovka:Dokumenty:novy',
                            array('cisty' => 1)) . '">Vytvořit nový nerozepsaný dokument.</a>',
                    'info_ext');

            $DokumentSpis = new DokumentSpis();
            $DokumentSubjekt = new DokumentSubjekt();
            $DokumentPrilohy = new DokumentPrilohy();

            $spisy = $DokumentSpis->spisy($dokument->id);
            $this->template->Spisy = $spisy;

            $subjekty = $DokumentSubjekt->subjekty($dokument->id);
            $this->template->Subjekty = $subjekty;

            $prilohy = $DokumentPrilohy->prilohy($dokument->id);
            $this->template->Prilohy = $prilohy;

            if ($this->typ_evidence == 'priorace') {
                // Nacteni souvisejicicho dokumentu
                $Souvisejici = new SouvisejiciDokument();
                $this->template->SouvisejiciDokumenty = $Souvisejici->souvisejici($dokument->id);
            }
        } else {

            if (Acl::isInRole('podatelna')) {
                $dokument_typ_id = 1;
            } else {
                $dokument_typ_id = 2;
            }

            $pred_priprava = array(
                "nazev" => "",
                "popis" => "",
                "stav" => 0,
                "dokument_typ_id" => $dokument_typ_id,
                "zpusob_doruceni_id" => null,
                "zpusob_vyrizeni_id" => null,
                "spousteci_udalost_id" => null,
                "cislo_jednaci_odesilatele" => "",
                "datum_vzniku" => date('Y-m-d H:i:s'),
                "lhuta" => "30",
                "poznamka" => "",
            );
            $dokument = $Dokumenty->ulozit($pred_priprava);

            if ($spis_id) {
                $DokumentSpis = new DokumentSpis();
                $DokumentSpis->pripojit($dokument->id, $spis_id);
                $spisy = $DokumentSpis->spisy($dokument->id);
                $this->template->Spisy = $spisy;
            } else {
                $this->template->Spisy = null;
            }


            $this->template->Subjekty = null;
            $this->template->Prilohy = null;
            $this->template->SouvisejiciDokumenty = null;
            $this->template->Typ_evidence = $this->typ_evidence;
        }

        $user = UserModel::getUser($this->user->getIdentity()->id, 1);
        $this->template->Prideleno = Osoba::displayName($user->identity);

        $CJ = new CisloJednaci();
        $this->template->cjednaci = $CJ->generuj();

        $this->template->typy_dokumentu = Dokument::typDokumentu();

        if ($dokument) {
            $this->template->Dok = $dokument;
        } else {
            $this->template->Dok = null;
            $this->flashMessage('Dokument není připraven k vytvoření', 'warning');
        }

        $this->template->novyForm = $this['novyForm'];
    }

    public function renderOdpoved()
    {

        $Dokumenty = new Dokument();

        $dokument_id = $this->getParameter('id', null);
        $dok = $Dokumenty->getInfo($dokument_id);

        if ($dok) {

            $args_rozd = array();
            $args_rozd['where'] = array(
                array('stav=%i', 0),
                array('dokument_typ_id=%i', 2),
                array('cislo_jednaci=%s', $dok->cislo_jednaci),
                array('user_created=%i', $this->user->getIdentity()->id)
            );
            $args_rozd['order'] = array('date_created' => 'DESC');

            $rozdelany_dokument = $Dokumenty->seznamKlasicky($args_rozd);

            if (count($rozdelany_dokument) > 0) {
                $dok_odpoved = $rozdelany_dokument[0];
                // odpoved jiz existuje, tak ji nacteme
                $DokumentSpis = new DokumentSpis();
                $DokumentSubjekt = new DokumentSubjekt();
                $DokumentPrilohy = new DokumentPrilohy();

                $spisy = $DokumentSpis->spisy($dok_odpoved->id);
                $this->template->Spisy = $spisy;

                $subjekty = $DokumentSubjekt->subjekty($dok_odpoved->id);
                $this->template->Subjekty = $subjekty;

                $prilohy = $DokumentPrilohy->prilohy($dok_odpoved->id);
                $this->template->Prilohy = $prilohy;

                $user = UserModel::getUser($this->user->getIdentity()->id, 1);
                $this->template->Prideleno = Osoba::displayName($user->identity);

                $CJ = new CisloJednaci();
                $this->template->Typ_evidence = $this->typ_evidence;
                if ($this->typ_evidence == 'priorace') {
                    // Nacteni souvisejicicho dokumentu
                    $Souvisejici = new SouvisejiciDokument();
                    $this->template->SouvisejiciDokumenty = $Souvisejici->souvisejici($dok_odpoved->id);
                } else if ($this->typ_evidence == 'sberny_arch') {
                    // sberny arch
                    //$dok_odpoved->poradi = $dok_odpoved->poradi;
                }

                $this->template->cjednaci = $CJ->nacti($dok->cislo_jednaci_id);

                $this->template->Dok = $dok_odpoved;
            } else {
                // totozna odpoved neexistuje
                // nalezeni nejvyssiho cisla poradi v ramci spisu
                $poradi = $Dokumenty->getMaxPoradi($dok->cislo_jednaci_id);

                $pred_priprava = array(
                    "nazev" => $dok->nazev,
                    "popis" => $dok->popis,
                    "stav" => 0,
                    "dokument_typ_id" => 2,
                    "zpusob_doruceni_id" => null,
                    "cislo_jednaci_id" => $dok->cislo_jednaci_id,
                    "cislo_jednaci" => $dok->cislo_jednaci,
                    "podaci_denik" => $dok->podaci_denik,
                    "podaci_denik_poradi" => $dok->podaci_denik_poradi,
                    "podaci_denik_rok" => $dok->podaci_denik_rok,
                    "poradi" => ($poradi),
                    "cislo_jednaci_odesilatele" => $dok->cislo_jednaci_odesilatele,
                    "datum_vzniku" => date('Y-m-d H:i:s'),
                    "lhuta" => "30",
                    "poznamka" => $dok->poznamka,
                    "zmocneni_id" => null,
                    "spisovy_znak_id" => $dok->spisovy_znak_id,
                    "skartacni_znak" => $dok->skartacni_znak,
                    "skartacni_lhuta" => $dok->skartacni_lhuta,
                    "spousteci_udalost_id" => $dok->spousteci_udalost_id
                );
                $dok_odpoved = $Dokumenty->ulozit($pred_priprava);

                if ($dok_odpoved) {

                    $DokumentSpis = new DokumentSpis();
                    $DokumentSubjekt = new DokumentSubjekt();
                    $DokumentPrilohy = new DokumentPrilohy();

                    // kopirovani spisu
                    $spisy_old = $DokumentSpis->spisy($dokument_id);
                    if (count($spisy_old) > 0) {
                        foreach ($spisy_old as $spis) {
                            $DokumentSpis->pripojit($dok_odpoved->id, $spis->id);
                        }
                    }
                    $spisy_new = $DokumentSpis->spisy($dok_odpoved->id);
                    $this->template->Spisy = $spisy_new;

                    // kopirovani subjektu
                    $subjekty_old = $DokumentSubjekt->subjekty($dokument_id);
                    if (count($subjekty_old) > 0) {
                        foreach ($subjekty_old as $subjekt) {
                            $rezim = $subjekt->rezim_subjektu;
                            if ($rezim == 'O')
                                $rezim = 'A';
                            else if ($rezim == 'A')
                                $rezim = 'O';
                            $DokumentSubjekt->pripojit($dok_odpoved->id, $subjekt->id, $rezim);
                        }
                    }
                    $subjekty_new = $DokumentSubjekt->subjekty($dok_odpoved->id);
                    $this->template->Subjekty = $subjekty_new;

                    // kopirovani prilohy
                    $prilohy_old = $DokumentPrilohy->prilohy($dokument_id);

                    if (count($prilohy_old) > 0) {
                        foreach ($prilohy_old as $priloha) {
                            if ($priloha->typ == 1 || $priloha->typ == 2 || $priloha->typ == 3) {
                                $DokumentPrilohy->pripojit($dok_odpoved->id, $priloha->id);
                            }
                        }
                    }
                    $prilohy_new = $DokumentPrilohy->prilohy($dok_odpoved->id);
                    $this->template->Prilohy = $prilohy_new;

                    $user = UserModel::getUser($this->user->getIdentity()->id, 1);
                    $this->template->Prideleno = Osoba::displayName($user->identity);

                    $CJ = new CisloJednaci();
                    $this->template->Typ_evidence = $this->typ_evidence;
                    $this->template->SouvisejiciDokumenty = null;
                    if ($this->typ_evidence == 'priorace') {
                        // priorace - Nacteni souvisejicicho dokumentu
                        $Souvisejici = new SouvisejiciDokument();
                        $Souvisejici->spojit($dok_odpoved->id, $dokument_id);
                        $this->template->SouvisejiciDokumenty = $Souvisejici->souvisejici($dok_odpoved->id);
                    } else if ($this->typ_evidence == 'sberny_arch') {
                        // sberny arch
                        //$dok_odpoved->poradi = $dok_odpoved->poradi;
                    }

                    $this->template->cjednaci = $CJ->nacti($dok->cislo_jednaci_id);
                    $this->template->Dok = $dok_odpoved;
                } else {
                    $this->template->Dok = null;
                    $this->flashMessage('Dokument není připraven k vytvoření', 'warning');
                }
            }

            $this->odpoved = true;
            $this->template->odpoved_na_dokument = true;

            $this->template->typy_dokumentu = Dokument::typDokumentu();

            $this->template->novyForm = $this['odpovedForm'];
            $this->setView('novy');
        } else {
            $this->template->Dok = null;
            $this->flashMessage('Dokument neexistuje', 'warning');
            $this->redirect(':Spisovka:Dokumenty:default');
        }
    }

    public function renderDownload()
    {

        $dokument_id = $this->getParameter('id', null);
        $file_id = $this->getParameter('file', null);

        $DokumentPrilohy = new DokumentPrilohy();
        $prilohy = $DokumentPrilohy->prilohy($dokument_id);
        if (array_key_exists($file_id, $prilohy)) {

            $DownloadFile = $this->storage;
            $FileModel = new FileModel();
            $file = $FileModel->getInfo($file_id);
            $res = $DownloadFile->download($file);
            if ($res == 0) {
                $this->terminate();
            } else if ($res == 1) {
                // not found
                $this->flashMessage('Požadovaný soubor nenalezen!', 'warning');
                $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
            } else if ($res == 2) {
                $this->flashMessage('Chyba při stahování!', 'warning');
                $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
            } else if ($res == 3) {
                $this->flashMessage('Neoprávněné stahování! Nemáte povolení stáhnout zmíněný soubor!',
                        'warning');
                $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
            }
        } else {
            $this->flashMessage('Neoprávněné stahování! Nemáte povolení stáhnout cizí soubor!',
                    'warning');
            $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
        }
    }

    public function renderZmocneni()
    {
        
    }

    public function renderHistorie()
    {

        $dokument_id = $this->getParameter('id', null);

        $Log = new LogModel();
        $historie = $Log->historieDokumentu($dokument_id, 1000);

        $this->template->historie = $historie;
    }

    public function actionOdeslat()
    {

        $Dokument = new Dokument();

        // Nacteni parametru
        $dokument_id = $this->getParameter('id', null);
        $dokument = $Dokument->getInfo($dokument_id, "subjekty,soubory,odeslani");

        if ($dokument) {
            // dokument zobrazime
            $this->template->Dok = $dokument;

            // Neprováděj prozatím žádné kontroly. Nechceme, aby se uživateli zobrazil příkaz "odeslat dokument" a po kliknutí obdržel chybové hlášení.
            // Kontrola se provede jen v detailu dokumentu, kde se rozhodne, zda se uživateli příkaz zobrazí nebo ne.
            // Kód určující oprávnění v akci "detail" bude muset být zcela přepsán.
            $UzivatelOpravnen = 1;

            if (!$UzivatelOpravnen) {
                $this->flashMessage('Nejste oprávněn odeslat tento dokument.', 'error');
                $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
            }

            // Prilohy
            $prilohy_celkem = 0;
            if (count($dokument->prilohy) > 0) {
                foreach ($dokument->prilohy as $p) {
                    $prilohy_celkem = $prilohy_celkem + $p->size;
                }
            }
            $this->template->PrilohyCelkovaVelikost = $prilohy_celkem;

            $this->template->ChybaPredOdeslani = 0;

            // Dokument se vyrizuje
            if ($dokument->stav_dokumentu == 3) {
                $this->template->Vyrizovani = 1;
            } else {
                $this->template->Vyrizovani = 0;
            }

            $this->template->FormUpravit = $this->getParameter('upravit', null);

            $SpisovyZnak = new SpisovyZnak();
            $this->template->SpisoveZnaky = $SpisovyZnak->seznam(null);

            $this->addComponent(new VyberPostovniZasilky(), 'druhZasilky');

            $this->template->OpravnenOdeslatDZ = $this->user->isAllowed('DatovaSchranka',
                    'odesilani');

            $this->template->ZpusobyOdeslani = ZpusobOdeslani::getZpusoby();

            $this->invalidateControl('dokspis');

            $max_vars = ini_get('max_input_vars');
            $safe_recipient_count = floor(($max_vars - 10) / 17);
            $recipient_count = 0;
            foreach ($dokument->subjekty as $subjekt)
                if ($subjekt->rezim_subjektu != 'O')
                    $recipient_count++;
            if ($recipient_count > $safe_recipient_count) {
                $this->flashMessage("Dokument má příliš mnoho adresátů a není možné jej odeslat. Maximální počet adresátů je $safe_recipient_count.",
                        'warning');
                $this->flashMessage("Limit je ovlivněn PHP nastavením \"max_input_vars\" na serveru.");
                $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
            }
        } else {
            // dokument neexistuje nebo se nepodarilo nacist
            $this->setView('noexist');
        }
    }

    public function renderOdeslat()
    {
        $this->template->odeslatForm = $this['odeslatForm'];
    }

    public function actionIsdsovereni()
    {
        $this->template->error = 0;
        $this->template->vysledek = "";
        $dokument_id = $this->getParameter('id');
        if ($dokument_id) {
            $Dokument = new Dokument();
            $dokument_info = $Dokument->getInfo($dokument_id, "soubory");

            if ($dokument_info) {
                $nalezeno = 0;
                foreach ($dokument_info->prilohy as $file) {
                    if (strpos($file->nazev, "zfo") !== false) {
                        if (!empty($file->id)) {
                            // nalezeno ZFO
                            $nalezeno = 1;

                            // Nacteni originalu DS
                            $DownloadFile = $this->storage;
                            $source = $DownloadFile->download($file, 1);
                            //echo $source;
                            if ($source) {

                                $isds = new ISDS_Spisovka();
                                $_pripojeno = false;
                                try {
                                    $isds->pripojit();
                                    $_pripojeno = true;
                                } catch (Exception $e) {
                                    $_chyba = $e->getMessage();
                                }
                                if ($_pripojeno) {

                                    if ($isds->AuthenticateMessage($source)) {
                                        $this->template->vysledek = "Datová zpráva byla ověřena a je platná.";
                                    } else {
                                        $this->template->error = 4;
                                        $this->template->vysledek = "Datová zpráva byla ověřena, ale není platná!" .
                                                "<br />" .
                                                'ISDS zpráva: ' . $isds->error();
                                    }
                                } else {
                                    $this->template->error = 3;
                                    $this->template->vysledek = "Nepodařilo se připojit k ISDS schránce!" .
                                            "<br />" . $_chyba;
                                }
                            }
                        }
                    }
                }

                if ($nalezeno == 0) {
                    // nenalezena zadna datova zprava
                    $this->template->error = 2;
                    $this->template->vysledek = "Nebyla nalezena datová zpráva k ověření!";
                }
            } else {
                $this->template->vysledek = "Nebyl nalezen dokument!";
                $this->template->error = 1;
            }
        } else {
            $this->template->vysledek = "Neplatný parametr!";
            $this->template->error = 1;
        }
    }

    protected function createComponentNovyForm()
    {
        $form = $this->createNovyOrOdpovedForm();
        $form->addText('lhuta', 'Lhůta k vyřízení:', 5, 15)
                ->addRule(Nette\Forms\Form::FILLED, 'Lhůta k vyřízení musí být vyplněna!')
                ->addRule(Nette\Forms\Form::NUMERIC, 'Lhůta k vyřízení musí být číslo')
                ->setValue('30');
        $form->addTextArea('predani_poznamka', 'Poznámka:', 80, 3);

        $form['zpusob_doruceni_id']->setDefaultValue(5); // v listinné podobě

        $form->addSubmit('novy_pridat', 'Vytvořit dokument a založit nový');
        $form['novy_pridat']->onClick[] = array($this, 'vytvoritClicked');

        return $form;
    }

    protected function createComponentOdpovedForm()
    {
        $form = $this->createNovyOrOdpovedForm();
        return $form;
    }

    protected function createNovyOrOdpovedForm()
    {
        $dok = null;
        if (isset($this->template->Dok)) {
            $dokument_id = isset($this->template->Dok->id) ? $this->template->Dok->id : 0;
            $dok = $this->template->Dok;
        } else {
            $dokument_id = 0;
        }

        if (Acl::isInRole('admin,superadmin'))
            $povolene_typy_dokumentu = Dokument::typDokumentu(null, 4);
        else if (Acl::isInRole('podatelna'))
            $povolene_typy_dokumentu = Dokument::typDokumentu(null, 2);
        else
            $povolene_typy_dokumentu = Dokument::typDokumentu(null, 1);

        $zpusob_doruceni = Dokument::zpusobDoruceni(2);

        $form = new Spisovka\Form();
        $form->addHidden('id')
                ->setValue($dokument_id);
        $form->addHidden('odpoved')
                ->setValue($this->odpoved === true ? 1 : 0);
        $form->addHidden('predano_user');
        $form->addHidden('predano_org');
        $form->addHidden('predano_poznamka');

        if ($this->typ_evidence == 'sberny_arch') {
            $form->addText('poradi', 'Pořadí dokumentu ve sberném archu:', 4, 4)
                            ->setValue(@$dok->poradi)
                    ->controlPrototype->readonly = TRUE;
        }

        if (isset($dok->nazev) && $dok->nazev == "(bez názvu)")
            $dok->nazev = "";
        $form->addText('nazev', 'Věc:', 80, 250)
                ->setValue(@$dok->nazev);
        if (!Acl::isInRole('podatelna')) {
            $form['nazev']->addRule(Nette\Forms\Form::FILLED,
                    'Název dokumentu (věc) musí být vyplněno!');
        }

        $form->addTextArea('popis', 'Stručný popis:', 80, 3)
                ->setValue(@$dok->popis);
        $form->addSelect('dokument_typ_id', 'Typ Dokumentu:', $povolene_typy_dokumentu)
                ->setValue(@$dok->typ_dokumentu->id);
        $form->addText('cislo_jednaci_odesilatele', 'Číslo jednací odesilatele:', 50, 50)
                ->setValue(@$dok->cislo_jednaci_odesilatele);

        $datum = date('d.m.Y');
        $cas = date('H:i:s');

        $form->addDatePicker('datum_vzniku', 'Datum doručení/vzniku:', 10)
                ->setValue($datum);
        $form->addText('datum_vzniku_cas', 'Čas doručení:', 10, 15)
                ->setValue($cas);

        $form->addSelect('zpusob_doruceni_id', 'Způsob doručení:', $zpusob_doruceni);

        $form->addText('cislo_doporuceneho_dopisu', 'Číslo doporučeného dopisu:', 50, 50)
                ->setValue(@$dok->cislo_doporuceneho_dopisu);

        $form->addText('pocet_listu', 'Počet listů:', 5, 10)
                ->setValue(@$dok->pocet_listu)->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet listů musí být číslo');
        $form->addText('pocet_priloh', 'Počet příloh:', 5, 10)
                ->setValue(@$dok->pocet_priloh)->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet příloh musí být číslo');
        $form->addText('typ_prilohy', 'Typ přílohy:', 20, 50)
                ->setValue(@$dok->typ_prilohy);


        $form->addSubmit('novy', 'Vytvořit dokument');
        $form['novy']->onClick[] = array($this, 'vytvoritClicked');

        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'stornoSeznamClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form;
    }

    public function vytvoritClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $Dokument = new Dokument();

        $dokument_id = $data['id'];
        $data['stav'] = 1;

        // uprava casu
        $data['datum_vzniku'] = $data['datum_vzniku'] . " " . $data['datum_vzniku_cas'];
        unset($data['datum_vzniku_cas']);

        // predani - neni ve formulari odpovedi
        $predani_poznamka = isset($data['predani_poznamka']) ? $data['predani_poznamka'] : '';

        unset($data['predani_poznamka'], $data['id'], $data['version']);

        try {

            // [P.L.] 2012-04-13   Pridany zakladni kontroly
            // TODO [T.V.] 2012-04-23 - zkontrolovat na novou podobu
            $result = $Dokument->select(array(array('id=%i', $dokument_id)));
            if (count($result) != 1) {
                throw new LogicException("Rozepsaný dokument ID $dokument_id nenalezen.", 1);
            }
            $row = $result->fetch();
            $stav = $row['stav'];
            if ($stav != 0) {
                throw new LogicException("Dokument ID $dokument_id je již vytvořen.", 2);
            }
            unset($result);

            // Poznamka: c.j. se v pripade noveho dokumentu generuje az na pokyn uzivatele
            // a u odpovedi jsou sloupce c.j. vyplneny uz pri vytvareni odpovedi
            $CJ = new CisloJednaci();
            $data['jid'] = $CJ->dejAppId() . "-ESS-$dokument_id";

            $dd = clone $data; // document data
            unset($dd['odpoved'], $dd['predano_user'], $dd['predano_org'], $dd['predano_poznamka']);
            $dokument = $Dokument->ulozit($dd, $dokument_id);

            if ($dokument) {
                $Workflow = new Workflow();
                $Workflow->vytvorit($dokument_id, $predani_poznamka);

                $Log = new LogModel();
                $Log->logDokument($dokument_id, LogModel::DOK_NOVY);

                // Vytvoreni spisu noveho archu
                if ($this->typ_evidence == 'sberny_arch' && isset($data['cislo_jednaci'])) {

                    $Spis = new Spis();
                    $spis = $Spis->findByName($data['cislo_jednaci']);
                    if (!$spis) {
                        // vytvorime spis
                        $spis_new = array(
                            'parent_id' => 1,
                            'nazev' => $data['cislo_jednaci'],
                            'popis' => $data['popis'],
                            'spousteci_udalost_id' => 3,
                            'skartacni_znak' => 'S',
                            'skartacni_lhuta' => '10',
                            'typ' => 'S',
                            'stav' => 1
                        );
                        $spis_id = $Spis->vytvorit($spis_new);
                        $spis = $Spis->getInfo($spis_id);
                    }

                    // pripojime
                    if ($spis) {
                        $DokumentSpis = new DokumentSpis();
                        $DokumentSpis->pripojit($dokument_id, $spis->id);
                    }
                }

                if ($data['odpoved'] == 1) {
                    $this->flashMessage('Odpověď byla vytvořena.');
                    $this->forward(':Spisovka:Dokumenty:kvyrizeni', array('id' => $dokument_id));
                } else {
                    $this->flashMessage('Dokument byl vytvořen.');

                    if (!empty($data['predano_user']) || !empty($data['predano_org'])) {
                        /* Dokument predan */
                        $Workflow->predat($dokument_id, $data['predano_user'],
                                $data['predano_org'], $data['predano_poznamka']);
                        $this->flashMessage('Dokument předán zaměstnanci nebo organizační jednotce.');
                    }

                    $name = $button->getName();
                    if ($name == "novy_pridat")
                        $this->redirect(':Spisovka:Dokumenty:novy');
                    else
                        $this->redirect(':Spisovka:Dokumenty:detail',
                                array('id' => $dokument_id));
                }
            } else {
                $this->flashMessage('Dokument se nepodařilo vytvořit.', 'warning');
            }
        } catch (DibiException $e) {
            $this->flashMessage('Dokument se nepodařilo vytvořit.', 'warning');
            $this->flashMessage('CHYBA: ' . $e->getMessage(), 'warning');
        } catch (LogicException $e) {
            $this->flashMessage('Kontrola platnosti selhala:' . $e->getMessage(), 'warning');
            if ($e->getCode() == 2) {
                $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
            }
        }
    }

    public function stornoClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $dokument_id = $data['id'];
        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function stornoSeznamClicked()
    {
        $this->redirect(':Spisovka:Dokumenty:default');
    }

    protected function createComponentMetadataForm()
    {
        $Dok = $this->template->Dok;

        $zpusob_doruceni = Dokument::zpusobDoruceni(2);
        $zpusob_doruceni[0] = '(není zadán)';
        ksort($zpusob_doruceni);
        
        $form = new Spisovka\Form();
        $form->addHidden('id');

        $nazev_control = $form->addText('nazev', 'Věc:', 80, 250);                
        if (!Acl::isInRole('podatelna')) {
            $nazev_control->addRule(Nette\Forms\Form::FILLED,
                    'Název dokumentu (věc) musí být vyplněno!');
        }

        $form->addTextArea('popis', 'Stručný popis:', 80, 3);
        
        if (Acl::isInRole('admin,superadmin'))
            $povolene_typy_dokumentu = Dokument::typDokumentu(null, 4);
        else if (Acl::isInRole('podatelna'))
            $povolene_typy_dokumentu = Dokument::typDokumentu(null, 2);
        else
            $povolene_typy_dokumentu = Dokument::typDokumentu(null, 1);

        if (in_array($Dok->typ_dokumentu->id, array_keys($povolene_typy_dokumentu))
            && count($povolene_typy_dokumentu) > 1) {
            $form->addSelect('dokument_typ_id', 'Typ Dokumentu:', $povolene_typy_dokumentu);
        }

        $form->addText('cislo_jednaci_odesilatele', 'Číslo jednací odesilatele:', 50, 50);
        $form->addDatePicker('datum_vzniku', 'Datum doručení/vzniku:', 10);
        $form->addText('datum_vzniku_cas', 'Čas doručení:', 10, 15);
        // doručení emailem a DS nastavuje systém, to uživatel nesmí měnit
        if ($this->template->isRozdelany && $Dok->typ_dokumentu->smer == 0
                && !in_array($Dok->zpusob_doruceni_id, [1, 2]))
            $form->addSelect('zpusob_doruceni_id', 'Způsob doručení:', $zpusob_doruceni);

        $form->addText('cislo_doporuceneho_dopisu', 'Číslo doporučeného dopisu:', 50, 50);
        $form->addTextArea('poznamka', 'Poznámka:', 80, 6);

        $form->addText('pocet_listu', 'Počet listů:', 5, 10)
                ->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet listů musí být číslo');
        $form->addText('pocet_priloh', 'Počet příloh:', 5, 10)
                ->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet příloh musí být číslo');
        $form->addText('typ_prilohy', 'Typ přílohy:', 20, 50);
                
        if ($Dok) {
            $nazev = ($Dok->nazev == "(bez názvu)") ? "" : $Dok->nazev;
            
            $unixtime = strtotime($Dok->datum_vzniku);
            if ($unixtime == 0) {
                $datum = date('d.m.Y');
                $cas = date('H:i:s');
            } else {
                $datum = date('d.m.Y', $unixtime);
                $cas = date('H:i:s', $unixtime);
                            
            }
            
            $form['id']->setDefaultValue($Dok->id);
            $form['nazev']->setDefaultValue($nazev);
            $form['popis']->setDefaultValue($Dok->popis);
            $form['cislo_jednaci_odesilatele']->setDefaultValue($Dok->cislo_jednaci_odesilatele);
            $form['datum_vzniku']->setDefaultValue($datum);
            $form['datum_vzniku_cas']->setDefaultValue($cas);
            if (isset($form['zpusob_doruceni_id']))
                $form['zpusob_doruceni_id']->setDefaultValue($Dok->zpusob_doruceni_id);
            $form['cislo_doporuceneho_dopisu']->setDefaultValue($Dok->cislo_doporuceneho_dopisu);
            $form['poznamka']->setDefaultValue($Dok->poznamka);
            $form['pocet_listu']->setDefaultValue($Dok->pocet_listu);
            $form['pocet_priloh']->setDefaultValue($Dok->pocet_priloh);
            $form['typ_prilohy']->setDefaultValue($Dok->typ_prilohy);
            if (isset($form['dokument_typ_id']))
                $form['dokument_typ_id']->setDefaultValue($Dok->typ_dokumentu->id);
        }
        
        $submit = $form->addSubmit('upravit', 'Uložit');
        $submit->onClick[] = array($this, 'upravitMetadataClicked');

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

    public function upravitMetadataClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $Dokument = new Dokument();
        $dokument_id = $data['id'];
        $dok = $Dokument->getInfo($dokument_id);

        //Nette\Diagnostics\Debugger::dump($data);

        if (!($dok->stav_dokumentu == 1 || $this->user->isInRole('superadmin'))) {
            // needitovatelne skryte polozky
            $data['datum_vzniku'] = $dok->datum_vzniku;
            $data['dokument_typ_id'] = $dok->typ_dokumentu->id;
            $data['zpusob_doruceni_id'] = $dok->zpusob_doruceni_id;

            // P.L. ne kazdy uzivatel ma pravo menit tyto udaje a pole formulare v tom pripade pak bude prazdne
            if (empty($data['cislo_doporuceneho_dopisu']))
                unset($data['cislo_doporuceneho_dopisu']);
            if (empty($data['cislo_jednaci_odesilatele']))
                unset($data['cislo_jednaci_odesilatele']);

            unset($data['datum_vzniku_cas']);
        } else {
            // uprava casu
            if (isset($data['datum_vzniku'])) {
                $data['datum_vzniku'] = $data['datum_vzniku'] . " " . $data['datum_vzniku_cas'];
            }
            unset($data['datum_vzniku_cas']);
        }

        //Nette\Diagnostics\Debugger::dump($data); exit;
        //Nette\Diagnostics\Debugger::dump($dok); exit;

        try {
            $Dokument->ulozit($data, $dokument_id);

            $Log = new LogModel();
            $Log->logDokument($dokument_id, LogModel::DOK_ZMENEN,
                    'Upravena metadata dokumentu.');

            $this->flashMessage('Dokument "' . $dok->cislo_jednaci . '"  byl upraven.');
            $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
        } catch (DibiException $e) {
            $this->flashMessage('Dokument "' . $dok->cislo_jednaci . '" se nepodařilo upravit.',
                    'warning');
            $this->flashMessage('CHYBA: ' . $e->getMessage(), 'warning');
        }
    }

    protected function createComponentVyrizovaniForm()
    {

        $zpusob_vyrizeni = Dokument::zpusobVyrizeni(1);

        $SpisovyZnak = new SpisovyZnak();
        $spousteci_udalost = $SpisovyZnak->spousteci_udalost(null, 1);

        $Dok = @$this->template->Dok;

        $form = new Spisovka\Form();
        $form->addHidden('id')
                ->setValue(@$Dok->id);
        $form->addSelect('zpusob_vyrizeni_id', 'Způsob vyřízení:', $zpusob_vyrizeni)
                ->setValue(@$Dok->zpusob_vyrizeni_id);

        $unixtime = strtotime(@$Dok->datum_vyrizeni);
        if ($unixtime == 0) {
            $datum = date('d.m.Y');
            $cas = date('H:i:s');
        } else {
            $datum = date('d.m.Y', $unixtime);
            $cas = date('H:i:s', $unixtime);
        }

        $form->addDatePicker('datum_vyrizeni', 'Datum vyřízení:', 10)
                ->setValue($datum);
        $form->addText('datum_vyrizeni_cas', 'Čas vyřízení:', 10, 15)
                ->setValue($cas);

        $form->addComponent(new SpisovyZnakComponent(), 'spisovy_znak_id');
        $form->getComponent('spisovy_znak_id')->setValue(@$Dok->spisovy_znak_id)
        ;

        $form->addTextArea('ulozeni_dokumentu', 'Uložení dokumentu:', 80, 6)
                ->setValue(@$Dok->ulozeni_dokumentu);
        $form->addTextArea('poznamka_vyrizeni', 'Poznámka k vyřízení:', 80, 6)
                ->setValue(@$Dok->poznamka_vyrizeni);

        $form->addText('skartacni_znak', 'Skartační znak: ', 3, 3)
                        ->setValue(@$Dok->skartacni_znak)
                ->controlPrototype->readonly = TRUE;
        //$form->addSelect('skartacni_znak', 'Skartační znak:', $skar_znak)
        //        ->setValue(@$Dok->skartacni_znak)
        //        //->controlPrototype->onchange("selectReadOnly(this);");
        $form->addText('skartacni_lhuta', 'Skartační lhuta: ', 5, 5)
                        ->setValue(@$Dok->skartacni_lhuta)
                ->controlPrototype->readonly = TRUE;
        $form->addSelect('spousteci_udalost_id', 'Spouštěcí událost: ', $spousteci_udalost)
                        ->setValue(empty($Dok->spousteci_udalost_id) ? 3 : @$Dok->spousteci_udalost_id )
                ->controlPrototype->readonly = TRUE;

        $form->addText('vyrizeni_pocet_listu', 'Počet listů:', 5, 10)
                ->setValue(@$Dok->vyrizeni_pocet_listu)->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet listů musí být číslo');
        $form->addText('vyrizeni_pocet_priloh', 'Počet příloh:', 5, 10)
                ->setValue(@$Dok->vyrizeni_pocet_priloh)->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet příloh musí být číslo');
        $form->addText('vyrizeni_typ_prilohy', 'Typ přílohy:', 20, 50)
                ->setValue(@$Dok->vyrizeni_typ_prilohy);


//                ->addRule(Form::FILLED, 'Název dokumentu (věc) musí být vyplněno!');

        $form->addSubmit('upravit', 'Uložit')
                ->onClick[] = array($this, 'upravitVyrizeniClicked');
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

    public function upravitVyrizeniClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $dokument_id = $data['id'];

        // uprava casu
        $data['datum_vyrizeni'] = $data['datum_vyrizeni'] . " " . $data['datum_vyrizeni_cas'];
        unset($data['datum_vyrizeni_cas']);

        $Dokument = new Dokument();

        $dok = $Dokument->getInfo($dokument_id);

        try {            
            $Dokument->ulozit($data, $dokument_id);

            $Log = new LogModel();
            $Log->logDokument($dokument_id, LogModel::DOK_ZMENEN, 'Upravena data vyřízení.');

            $this->flashMessage('Dokument "' . $dok->cislo_jednaci . '"  byl upraven.');
        } catch (DibiException $e) {
            $this->flashMessage('Dokument "' . $dok->cislo_jednaci . '" se nepodařilo upravit.',
                    'warning');
            $this->flashMessage('CHYBA: ' . $e->getMessage(), 'warning');
        }
        $this->redirect('detail', array('id' => $dokument_id));
    }

    protected function createComponentUdalostForm()
    {

        $Dok = @$this->template->Dok;

        $form = new Spisovka\Form();
        $form->addHidden('id')
                ->setValue(@$Dok->id);

        $options = array(
            '1' => 'Dnešní den',
            '2' => 'Zadám datum',
            '3' => 'Datum určím až v budoucnu',
        );
        $form->addRadioList('udalost_typ', 'Jak spustit událost:', $options)
                ->setValue(1)
        ->controlPrototype->onclick("onChangeRadioButtonSpousteciUdalost();");

        $form->addDatePicker('datum_spousteci_udalosti', 'Datum spouštěcí události:')
                //->setDisabled() - nelze volat pri zpracovani odeslaneho formulare, vyresil jsem tedy v Javascriptu
                ->forbidPastDates();

        $form->addSubmit('ok', 'Potvrdit')
                ->onClick[] = array($this, 'udalostClicked');
        // $form->addSubmit('storno', 'Zrušit vyřízení')
        // ->setValidationScope(FALSE)
        // ->onClick[] = array($this, 'stornoClicked');
        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form;
    }

    public function udalostClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $dokument_id = $data['id'];
        $redir_params = array('id' => $dokument_id);
        $datum = null;

        $Dokumenty = new Dokument();
        $dokument = $Dokumenty->getInfo($dokument_id);

        $Workflow = new Workflow();
        if ($Workflow->prirazeny($dokument_id) && $dokument->stav_dokumentu == 4) {

            switch ($data['udalost_typ']) {
                case 1 :
                    $datum = date('Y-m-d');
                    $zprava = 'Událost byla spuštěna.';
                    break;

                case 2 :
                    if (empty($data['datum_spousteci_udalosti'])) {
                        $this->flashMessage('Nebyla zadáno datum spuštění.', 'warning');
                        // znovu zobraz formular
                        $redir_params['udalost'] = 1;
                    } else {
                        $datum = $data['datum_spousteci_udalosti'];
                        $zprava = 'Datum spuštění bylo nastaveno.';
                    }
                    break;

                case 3 :
                // nedelej nic
            }

            if (!empty($datum)) {
                $Workflow->spustitUdalost($dokument_id, $datum);
                $this->flashMessage($zprava, 'info');
            }
        } else
            $this->flashMessage('Nemáte oprávnění spustit událost.', 'warning');

        $this->redirect(':Spisovka:Dokumenty:detail', $redir_params);
    }

    protected function createComponentOdeslatForm()
    {
        $Dok = @$this->template->Dok;
        $zprava = "";
        $sznacka = "";
        
        if (isset($this->template->Dok->spisy) && is_array($this->template->Dok->spisy)) {
            $sznacka_A = array();
            foreach ($this->template->Dok->spisy as $spis) {
                $sznacka_A[] = $spis->nazev;
            }
            $sznacka = implode(", ", $sznacka_A);
        }
        $this->template->SpisovaZnacka = $sznacka;

        // odesilatele
        $ep = (new Spisovka\ConfigEpodatelna())->get();
        $odesilatele = array();

        if (count($ep['odeslani']) > 0) {
            foreach ($ep['odeslani'] as $odes_id => $odes) {
                if ($odes['aktivni'] == 1) {
                    if (empty($odes['jmeno'])) {
                        $odesilatele['epod' . $odes_id] = $odes['email'] . "[" . $odes['ucet'] . "]";
                    } else {
                        $odesilatele['epod' . $odes_id] = $odes['jmeno'] . " <" . $odes['email'] . "> [" . $odes['ucet'] . "]";
                    }
                }
            }
        }
        $user_info = $this->user->getIdentity();
        if (!empty($user_info->identity->email)) {
            $key = "user#" . Osoba::displayName($user_info->identity, 'jmeno') . "#" . $user_info->identity->email;
            $odesilatele[$key] = Osoba::displayName($user_info->identity, 'jmeno') . " <" . $user_info->identity->email . "> [zaměstnanec]";
        }

        $this->template->odesilatele = $odesilatele;

        $form = new Spisovka\Form();
        $form->addHidden('id')
                ->setValue(@$Dok->id);
        $form->addSelect('email_from', 'Odesílatel:', $odesilatele)
                // Nette hack, který zakáže validaci select boxu
                ->setPrompt('prompt pro Odesilatel');
        $form->addText('email_predmet', 'Předmět zprávy:', 80, 100)
                ->setValue(@$Dok->nazev);
        $form->addTextArea('email_text', 'Text:', 80, 15)
                ->setValue($zprava);

        $form->addText('isds_predmet', 'Předmět zprávy:', 80, 100)
                ->setValue(@$Dok->nazev);
        $form->addText('isds_cjednaci_odes', 'Číslo jendací odesílatele:', 40, 100)
                ->setValue(@$Dok->cislo_jednaci);
        $form->addText('isds_spis_odes', 'Spisová značka odesílatele:', 40, 100)
                ->setValue($sznacka);
        $form->addText('isds_cjednaci_adres', 'Číslo jendací adresáta:', 40, 100)
                ->setValue(@$Dok->cislo_jednaci_odesilatele);
        $form->addText('isds_spis_adres', 'Spisová značka adresáta:', 40, 100);
        $form->addCheckbox('isds_dvr', 'Do vlastních rukou? :')
                ->setValue(false);
        $form->addCheckbox('isds_fikce', 'Doručit fikcí? :')
                ->setValue(true);



        $form->addSubmit('odeslat', 'Předat podatelně či Odeslat')
                ->onClick[] = array($this, 'odeslatClicked');
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

    public function odeslatClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        //Nette\Diagnostics\Debugger::dump($data); exit;

        $dokument_id = $data['id'];
        $Dokument = new Dokument();
        $Subjekt = new Subjekt();
        $File = new FileModel();

        if (empty($dokument_id)) {
            $this->flashMessage('Ve formuláři odeslání dokumentu chybí jeho ID! Pravděpodobně došlo k překročení limitu počtu adresátů u dokumentu.',
                    'warning');
            $this->redirect(':Spisovka:Dokumenty:');
        }
        // nejprve ověř, že dokument existuje
        $doc = $Dokument->getInfo($dokument_id);
        if (!$doc) {
            $this->flashMessage('Nemohu načíst dokument. Dokument nebude odeslán.', 'warning');
            $this->redirect(':Spisovka:Dokumenty:');
        }

        $post_data = $this->getHttpRequest()->getPost();

        // Nette\Diagnostics\Debugger::dump($post_data);
        // Nette\Diagnostics\Debugger::dump($data);
        //echo "<pre>";
        //echo "\n\nPřílohy:\n\n";
        $prilohy = array();
        if (isset($post_data['prilohy'])) {
            if (count($post_data['prilohy']) > 0) {

                $DownloadFile = $this->storage;

                foreach (array_keys($post_data['prilohy']) as $file_id) {
                    $priloha = $File->getInfo($file_id);
                    $priloha->tmp_file = $DownloadFile->download($priloha, 2);

                    $prilohy[$file_id] = $priloha;
                }
            } else {
                // zadne prilohy
            }
        } else {
            // zadne prilohy
        }


        //echo "\n\nAdresati:\n\n";

        if (isset($post_data['subjekt']) && count($post_data['subjekt']) > 0) {
            foreach ($post_data['subjekt'] as $subjekt_id => $metoda_odeslani) {
                $adresat = $Subjekt->getInfo($subjekt_id);
                //echo Subjekt::displayName($adresat) ."\n";
                //Nette\Diagnostics\Debugger::dump($adresat);

                $datum_odeslani = new DateTime();
                $epodatelna_id = null;
                $zprava_odes = '';
                $cena = null;
                $hmotnost = null;
                $druh_zasilky = null;
                $cislo_faxu = '';
                $stav = 0;
                $poznamka = null;

                if ($metoda_odeslani == 0) {
                    // neodesilat - nebudeme delat nic
                    //echo "  => neodesilat";
                    continue;
                } elseif ($metoda_odeslani == 1) {
                    // emailem
                    //echo "  => emailem";
                    if (!empty($adresat->email)) {

                        $data = array(
                            'dokument_id' => $dokument_id,
                            'email_from' => $post_data['email_from'][$subjekt_id],
                            'email_predmet' => $post_data['email_predmet'][$subjekt_id],
                            'email_text' => $post_data['email_text'][$subjekt_id],
                        );

                        if ($zprava = $this->odeslatEmailem($adresat, $data, $prilohy)) {
                            $Log = new LogModel();
                            $Log->logDokument($dokument_id, LogModel::DOK_ODESLAN,
                                    'Dokument odeslán emailem na adresu "' . Subjekt::displayName($adresat,
                                            'email') . '".');
                            $this->flashMessage('Zpráva na emailovou adresu "' . Subjekt::displayName($adresat,
                                            'email') . '" byla úspěšně odeslána.');
                            $stav = 2;
                        } else {
                            $Log = new LogModel();
                            $Log->logDokument($dokument_id, LogModel::DOK_NEODESLAN,
                                    'Dokument se nepodařilo odeslat emailem na adresu "' . Subjekt::displayName($adresat,
                                            'email') . '".');
                            $this->flashMessage('Zprávu na emailovou adresu "' . Subjekt::displayName($adresat,
                                            'email') . '" se nepodařilo odeslat!', 'warning');
                            $stav = 0;
                            continue;
                        }

                        if (isset($zprava['epodatelna_id'])) {
                            $epodatelna_id = $zprava['epodatelna_id'];
                        }
                        if (isset($zprava['zprava'])) {
                            $zprava_odes = $zprava['zprava'];
                        }
                    } else {
                        $this->flashMessage('Subjekt "' . Subjekt::displayName($adresat,
                                        'email') . '" nemá emailovou adresu. Zprávu tomuto adresátovi nelze poslat přes email!',
                                'warning');
                        continue;
                    }
                } elseif ($metoda_odeslani == 2) {
                    // isds
                    if (!$this->user->isAllowed('DatovaSchranka', 'odesilani')) {
                        $this->flashMessage('Nemáte oprávnění odesílat datové zprávy.',
                                'warning');
                        continue;
                    }

                    if (empty($adresat->id_isds)) {
                        $this->flashMessage('Subjekt "' . Subjekt::displayName($adresat,
                                        'jmeno') . '" nemá ID datové schránky. Zprávu tomuto adresátovi nelze poslat přes datovou schránku!',
                                'warning');
                        continue;
                    }

                    $data = array(
                        'dokument_id' => $dokument_id,
                        'isds_predmet' => $post_data['isds_predmet'][$subjekt_id],
                        'isds_cjednaci_odes' => $post_data['isds_cjednaci_odes'][$subjekt_id],
                        'isds_spis_odes' => $post_data['isds_spis_odes'][$subjekt_id],
                        'isds_cjednaci_adres' => $post_data['isds_cjednaci_adres'][$subjekt_id],
                        'isds_spis_adres' => $post_data['isds_spis_adres'][$subjekt_id],
                        'isds_dvr' => isset($post_data['isds_dvr'][$subjekt_id]) ? true : false,
                        'isds_fikce' => isset($post_data['isds_fikce'][$subjekt_id]) ? true : false,
                    );

                    if ($zprava = $this->odeslatISDS($adresat, $data, $prilohy)) {
                        $Log = new LogModel();
                        $Log->logDokument($dokument_id, LogModel::DOK_ODESLAN,
                                'Dokument odeslán datovou zprávou na adresu "' . Subjekt::displayName($adresat,
                                        'isds') . '".');
                        $this->flashMessage('Datová zpráva pro "' . Subjekt::displayName($adresat,
                                        'isds') . '" byla úspěšně odeslána do systému ISDS.');
                        $stav = 2;
                        if (!is_array($zprava)) {
                            $this->flashMessage('Datovou zprávu pro "' . Subjekt::displayName($adresat,
                                            'isds') . '" se nepodařilo uložit do e-podatelny.',
                                    'warning');
                            continue;
                        }
                    } else {
                        $Log = new LogModel();
                        $Log->logDokument($dokument_id, LogModel::DOK_NEODESLAN,
                                'Dokument se nepodařilo odeslat datovou zprávou na adresu "' . Subjekt::displayName($adresat,
                                        'isds') . '".');
                        $this->flashMessage('Datovou zprávu pro "' . Subjekt::displayName($adresat,
                                        'isds') . '" se nepodařilo odeslat do systému ISDS!',
                                'warning');
                        $stav = 0;
                        continue;
                    }

                    if (isset($zprava['epodatelna_id'])) {
                        $epodatelna_id = $zprava['epodatelna_id'];
                    }
                    if (isset($zprava['zprava'])) {
                        $zprava_odes = $zprava['zprava'];
                    }
                } else if ($metoda_odeslani == 3) {
                    // postou
                    if (isset($post_data['datum_odeslani_postou'][$subjekt_id])) {
                        $datum_odeslani = new DateTime($post_data['datum_odeslani_postou'][$subjekt_id]);
                    }

                    $druh_zasilky_form = @$post_data['druh_zasilky'][$subjekt_id];
                    if (count($druh_zasilky_form) > 0) {
                        $druh_zasilky = serialize(array_keys($druh_zasilky_form));
                    }

                    $cena = floatval($post_data['cena_zasilky'][$subjekt_id]);
                    $hmotnost = floatval($post_data['hmotnost_zasilky'][$subjekt_id]);
                    $poznamka = $post_data['poznamka'][$subjekt_id];
                    $stav = 1;

                    $this->flashMessage('Dokument předán na podatelnu k odeslání poštou na adresu "' . Subjekt::displayName($adresat) . '".');

                    $Log = new LogModel();
                    $Log->logDokument($dokument_id, LogModel::DOK_PREDODESLAN,
                            'Dokument předán na podatelnu k odeslání poštou na adresu "' . Subjekt::displayName($adresat) . '".');
                } else if ($metoda_odeslani == 4) {
                    // faxem
                    if (isset($post_data['datum_odeslani_faxu'][$subjekt_id])) {
                        $datum_odeslani = new DateTime($post_data['datum_odeslani_faxu'][$subjekt_id]);
                    }

                    $cislo_faxu = $post_data['cislo_faxu'][$subjekt_id];
                    $zprava_odes = $post_data['zprava_faxu'][$subjekt_id];
                    $stav = 1;

                    $this->flashMessage('Dokument předán na podatelnu k odeslání faxem na číslo "' . $cislo_faxu . '".');

                    $Log = new LogModel();
                    $Log->logDokument($dokument_id, LogModel::DOK_PREDODESLAN,
                            'Dokument předán na podatelnu k odeslání faxem na číslo "' . $cislo_faxu . '".');
                } else {
                    // jinak - externe (osobne, ...)

                    if (isset($post_data['datum_odeslani'][$subjekt_id])) {
                        $datum_odeslani = new DateTime($post_data['datum_odeslani'][$subjekt_id]);
                    }

                    $Log = new LogModel();
                    $Log->logDokument($dokument_id, LogModel::DOK_ODESLAN,
                            'Dokument odeslán způsobem "' . ZpusobOdeslani::getName($metoda_odeslani) . '" adresátovi "' . Subjekt::displayName($adresat,
                                    'jmeno') . '".');
                }

                // Zaznam do DB (dokument_odeslani)
                $DokumentOdeslani = new DokumentOdeslani();
                $row = array(
                    'dokument_id' => $dokument_id,
                    'subjekt_id' => $adresat->id,
                    'zpusob_odeslani_id' => (int) $metoda_odeslani,
                    'epodatelna_id' => $epodatelna_id,
                    'datum_odeslani' => $datum_odeslani,
                    'zprava' => $zprava_odes,
                    'druh_zasilky' => $druh_zasilky,
                    'cena' => $cena,
                    'hmotnost' => $hmotnost,
                    'cislo_faxu' => $cislo_faxu,
                    'stav' => $stav,
                    'poznamka' => $poznamka
                );
                $DokumentOdeslani->ulozit($row);
            }
        } else {
            // zadni adresati
        }

        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    protected function odeslatEmailem($adresat, $data, $prilohy)
    {

        $mail = new ESSMail;
        $mail->signed(1);

        try {
            if (!empty($data['email_from'])) {

                if (strpos($data['email_from'], "epod") !== false) {
                    $id_odes = substr($data['email_from'], 4);
                    $ep = (new Spisovka\ConfigEpodatelna())->get();
                    if (isset($ep['odeslani'][$id_odes])) {
                        $mail->setFromConfig($ep['odeslani'][$id_odes]);
                    } else {
                        $mail->setFromConfig();
                    }
                } else if (strpos($data['email_from'], "user") !== false) {

                    $user_part = explode("#", $data['email_from']);
                    $mail->setFrom($user_part[2], $user_part[1]);
                } else {
                    $mail->setFromConfig();
                }
            } else {
                $mail->setFromConfig();
            }

            if (strpos($adresat->email, ';') !== false) {
                $email_parse = explode(';', $adresat->email);
                foreach ($email_parse as $emp) {
                    $email = trim($emp);
                    $mail->addTo($email);
                }
            } elseif (strpos($adresat->email, ',') !== false) {
                $email_parse = explode(',', $adresat->email);
                foreach ($email_parse as $emp) {
                    $email = trim($emp);
                    $mail->addTo($email);
                }
            } else {
                $mail->addTo($adresat->email);
            }

            $mail->setSubject($data['email_predmet']);
            $mail->setBody($data['email_text']);

            if (count($prilohy) > 0) {
                foreach ($prilohy as $p) {
                    $mail->addAttachment($p->tmp_file);
                }
            }

            $mail->send();
        } catch (Exception $e) {
            $this->flashMessage('Chyba při odesílání emailu! ' . $e->getMessage(), 'error_ext');
            return false;
        }

        $source = "";
        if (file_exists(CLIENT_DIR . '/temp/tmp_email.eml')) {
            $source = CLIENT_DIR . '/temp/tmp_email.eml';
        }

        // Do epodatelny
        $UploadFile = $this->storage;

        // nacist email z ImapClient
        $imap = new ImapClientFile();
        if ($imap->open($source)) {
            $email_mess = $imap->get_head_message(0);
        } else {
            $email_mess = new stdClass();
            $email_mess->from_address = @$user_part[2];
            $mid = sha1(@$data['email_predmet'] . "#" . time() . "#" . @$user_part[2] . "#" . @$adresat->email);
            $email_mess->message_id = "<$mid@mail>";
            $email_mess->subject = $data['email_predmet'];
            $email_mess->to_address = $adresat->email;
        }


        if (isset($user_part)) {
            $email_config['ucet'] = $user_part[1];
            $email_config['email'] = $user_part[2];
        } else {
            $email_config['ucet'] = "uživatel";
            $email_config['email'] = $email_mess->from_address;
        }
        $adresat_popis = $email_config['ucet'] . ' [' . $email_config['email'] . ']';

        $user = $this->user->getIdentity();

        // zapis do epodatelny
        $Epodatelna = new Epodatelna();
        $zprava = array();
        $zprava['epodatelna_typ'] = 1;
        $zprava['poradi'] = $Epodatelna->getMax(1);
        $zprava['rok'] = date('Y');
        $zprava['email_id'] = $email_mess->message_id;
        $zprava['predmet'] = empty($email_mess->subject) ? $data['email_predmet'] : $email_mess->subject;
        if (empty($zprava['predmet']))
            $zprava['predmet'] = "(bez předmětu)";
        $zprava['popis'] = $data['email_text'];
        $zprava['odesilatel'] = $email_mess->to_address;
        $zprava['odesilatel_id'] = $adresat->id;
        $zprava['adresat'] = $adresat_popis;
        $zprava['prijato_dne'] = new DateTime();
        $zprava['doruceno_dne'] = new DateTime();
        $zprava['prijal_kdo'] = $user->id;
        $zprava['prijal_info'] = serialize($user->identity);

        $zprava['sha1_hash'] = $source ? sha1_file($source) : '';

        $prilohy = array();
        if (isset($email_mess->attachments)) {
            foreach ($email_mess->attachments as $pr) {

                $base_name = basename($pr['DataFile']);
                //echo $base_name ."<br>";

                $prilohy[] = array(
                    'name' => $pr['FileName'],
                    'size' => filesize($pr['DataFile']),
                    'mimetype' => FileModel::mimeType($pr['FileName']),
                    'id' => $base_name
                );
            }
        }
        $zprava['prilohy'] = serialize($prilohy);

        $zprava['evidence'] = 'spisovka';
        $zprava['dokument_id'] = $data['dokument_id'];
        $zprava['stav'] = 0;
        $zprava['stav_info'] = '';
        //$zprava['source'] = $z;
        //unset($mess->source);
        //$zprava['source'] = $mess;
        $zprava['file_id'] = null;

        if ($epod_id = $Epodatelna->insert($zprava)) {

            $data_file = array(
                'filename' => 'ep_email_' . $epod_id . '.eml',
                'dir' => 'EP-O-' . sprintf('%06d', $zprava['poradi']) . '-' . $zprava['rok'],
                'typ' => '5',
                'popis' => 'Emailová zpráva z epodatelny ' . $zprava['poradi'] . '-' . $zprava['rok']
                    //'popis'=>'Emailová zpráva'
            );

            $mess_source = "";
            if ($fp = @fopen($source, 'rb')) {
                $mess_source = fread($fp, filesize($source));
                @fclose($fp);
            }

            if ($file = $UploadFile->uploadEpodatelna($mess_source, $data_file)) {
                // ok
                $zprava['stav_info'] = 'Zpráva byla uložena';
                $zprava['file_id'] = $file->id;
                $Epodatelna->update(
                        array('stav' => 1,
                    'stav_info' => $zprava['stav_info'],
                    'file_id' => $file->id
                        ), array(array('id=%i', $epod_id))
                );
            } else {
                $zprava['stav_info'] = 'Originál zprávy se nepodařilo uložit';
                // false
            }
        } else {
            $zprava['stav_info'] = 'Zprávu se nepodařilo uložit';
        }




        return array(
            'source' => $source,
            'epodatelna_id' => $epod_id,
            'email_id' => $email_mess->message_id,
            'zprava' => $data['email_text']
        );
        //} else {
        //    return false;
        //}
    }

    protected function odeslatISDS($adresat, $data, $prilohy)
    {
        $id_mess = null;
        $mess = null;
        $epod_id = null;
        $zprava = null;
        $popis = null;
        
        try {
            $isds = new ISDS_Spisovka();
            $isds->pripojit();

            $dmEnvelope = array(
                "dbIDRecipient" => $adresat->id_isds,
                "cislo_jednaci" => $data['isds_cjednaci_odes'],
                "spisovy_znak" => $data['isds_spis_odes'],
                "vase_cj" => $data['isds_cjednaci_adres'],
                "vase_sznak" => $data['isds_spis_adres'],
                "k_rukam" => $data['isds_dvr'],
                "anotace" => $data['isds_predmet'],
                "do_vlastnich" => ($data['isds_dvr'] == true) ? 1 : 0,
                "doruceni_fikci" => ($data['isds_fikce'] == true) ? 0 : 1
            );

            $id_mess = $isds->odeslatZpravu($dmEnvelope, $prilohy);
            if (!$id_mess) {
                $this->flashMessage('Chyba ISDS: ' . $isds->error(), 'warning_ext');
                return false;
            }

            sleep(3);
            $odchozi_zpravy = $isds->seznamOdeslanychZprav(time() - 3600, time() + 3600);
            if (count($odchozi_zpravy) > 0) {
                foreach ($odchozi_zpravy as $oz) {
                    if ($oz->dmID == $id_mess) {
                        $mess = $oz;
                        break;
                    }
                }
            }
            if (is_null($mess)) {
                return false;
            }

            $popis = '';
            $popis .= "ID datové zprávy    : " . $mess->dmID . "\n"; // = 342682
            $popis .= "Věc, předmět zprávy : " . $mess->dmAnnotation . "\n"; //  = Vaše datová zpráva byla přijata
            $popis .= "\n";
            $popis .= "Číslo jednací odesílatele   : " . $mess->dmSenderRefNumber . "\n"; //  = AB-44656
            $popis .= "Spisová značka odesílatele : " . $mess->dmSenderIdent . "\n"; //  = ZN-161
            $popis .= "Číslo jednací příjemce     : " . $mess->dmRecipientRefNumber . "\n"; //  = KAV-34/06-ŘKAV/2010
            $popis .= "Spisová značka příjemce    : " . $mess->dmRecipientIdent . "\n"; //  = 0.06.00
            $popis .= "\n";
            $popis .= "Do vlastních rukou? : " . (!empty($mess->dmPersonalDelivery) ? "ano" : "ne") . "\n"; //  =
            $popis .= "Doručeno fikcí?     : " . (!empty($mess->dmAllowSubstDelivery) ? "ano" : "ne") . "\n"; //  =
            $popis .= "Zpráva určena pro   : " . $mess->dmToHands . "\n"; //  =
            $popis .= "\n";
            $popis .= "Odesílatel:\n";
            $popis .= "            " . $mess->dbIDSender . "\n"; //  = hjyaavk
            $popis .= "            " . $mess->dmSender . "\n"; //  = Město Milotice
            $popis .= "            " . $mess->dmSenderAddress . "\n"; //  = Kovářská 14/1, 37612 Milotice, CZ
            $popis .= "            " . $mess->dmSenderType . " - " . ISDS_Spisovka::typDS($mess->dmSenderType) . "\n"; //  = 10
            $popis .= "            org.jednotka: " . $mess->dmSenderOrgUnit . " [" . $mess->dmSenderOrgUnitNum . "]\n"; //  =
            $popis .= "\n";
            $popis .= "Příjemce:\n";
            $popis .= "            " . $mess->dbIDRecipient . "\n"; //  = pksakua
            $popis .= "            " . $mess->dmRecipient . "\n"; //  = Společnost pro výzkum a podporu OpenSource
            $popis .= "            " . $mess->dmRecipientAddress . "\n"; //  = 40501 Děčín, CZ
            //$popis .= "Je příjemce ne-OVM povýšený na OVM: ". $mess->dmDm->dmAmbiguousRecipient ."\n";//  =
            $popis .= "            org.jednotka: " . $mess->dmRecipientOrgUnit . " [" . $mess->dmRecipientOrgUnitNum . "]\n"; //  =
            $popis .= "\n";
            $popis .= "Status: " . $mess->dmMessageStatus . " - " . ISDS_Spisovka::stavZpravy($mess->dmMessageStatus) . "\n";
            $dt_dodani = strtotime($mess->dmDeliveryTime);
            $dt_doruceni = strtotime($mess->dmAcceptanceTime);
            $popis .= "Datum a čas dodání   : " . date("j.n.Y G:i:s", $dt_dodani) . " (" . $mess->dmDeliveryTime . ")\n"; //  =
            if ($dt_doruceni == 0) {
                $popis .= "Datum a čas doručení : (příjemce zprávu zatím nepřijal)\n"; //  =    
            } else {
                $popis .= "Datum a čas doručení : " . date("j.n.Y G:i:s", $dt_doruceni) . " (" . $mess->dmAcceptanceTime . ")\n"; //  =                    
            }
            $popis .= "Přibližná velikost všech příloh : " . $mess->dmAttachmentSize . "kB\n"; //  =
            //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleLaw ."\n";//  =
            //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleYear ."\n";//  =
            //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleSect ."\n";//  =
            //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitlePar ."\n";//  =
            //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitlePoint ."\n";//  =
            // Do epodatelny
            $UploadFile = $this->storage;

            $Epodatelna = new Epodatelna();
            $config = $isds->getConfig();
            $user = $this->user->getIdentity();

            $zprava = array();
            $zprava['epodatelna_typ'] = 1;
            $zprava['poradi'] = $Epodatelna->getMax(1);
            $zprava['rok'] = date('Y');
            $zprava['isds_id'] = $mess->dmID;
            $zprava['predmet'] = empty($mess->dmAnnotation) ? "(Datová zpráva bez předmětu)" : $mess->dmAnnotation;
            $zprava['popis'] = $popis;
            $zprava['odesilatel'] = $mess->dmRecipient . ', ' . $mess->dmRecipientAddress;
            $zprava['odesilatel_id'] = $adresat->id;
            $zprava['adresat'] = $config['ucet'] . ' [' . $config['idbox'] . ']';
            $zprava['prijato_dne'] = new DateTime();

            $zprava['doruceno_dne'] = new DateTime($mess->dmAcceptanceTime);

            $zprava['prijal_kdo'] = $user->id;
            $zprava['prijal_info'] = serialize($user->identity);

            $zprava['sha1_hash'] = '';

            $aprilohy = array();
            if (count($prilohy) > 0) {
                foreach ($prilohy as $index => $file) {
                    $aprilohy[] = array(
                        'name' => $file->real_name,
                        'size' => $file->size,
                        'mimetype' => $file->mime_type,
                        'id' => $index
                    );
                }
            }
            $zprava['prilohy'] = serialize($aprilohy);

            $zprava['evidence'] = 'spisovka';
            $zprava['dokument_id'] = $data['dokument_id'];
            $zprava['stav'] = 0;
            $zprava['stav_info'] = '';

            if ($epod_id = $Epodatelna->insert($zprava)) {

                /* Ulozeni podepsane ISDS zpravy */
                $data = array(
                    'filename' => 'ep_isds_' . $epod_id . '.zfo',
                    'dir' => 'EP-O-' . sprintf('%06d', $zprava['poradi']) . '-' . $zprava['rok'],
                    'typ' => '5',
                    'popis' => 'Podepsaný originál ISDS zprávy z epodatelny ' . $zprava['poradi'] . '-' . $zprava['rok']
                        //'popis'=>'Emailová zpráva'
                );

                $signedmess = $isds->SignedSentMessageDownload($id_mess);

                if ($file_o = $UploadFile->uploadEpodatelna($signedmess, $data)) {
                    // ok
                } else {
                    $zprava['stav_info'] = 'Originál zprávy se nepodařilo uložit';
                    // false
                }

                /* Ulozeni reprezentace zpravy */
                $data = array(
                    'filename' => 'ep_isds_' . $epod_id . '.bsr',
                    'dir' => 'EP-O-' . sprintf('%06d', $zprava['poradi']) . '-' . $zprava['rok'],
                    'typ' => '5',
                    'popis' => ' Byte-stream reprezentace ISDS zprávy z epodatelny ' . $zprava['poradi'] . '-' . $zprava['rok']
                        //'popis'=>'Emailová zpráva'
                );

                if ($file = $UploadFile->uploadEpodatelna(serialize($mess), $data)) {
                    // ok
                    $zprava['stav_info'] = 'Zpráva byla uložena';
                    //$zprava['file_id'] = $file->id ."-". $file_o->id;
                    $zprava['file_id'] = $file->id;
                    $Epodatelna->update(
                            array('stav' => 1,
                        'stav_info' => $zprava['stav_info'],
                        'file_id' => $file->id
                            ), array(array('id=%i', $epod_id))
                    );
                } else {
                    $zprava['stav_info'] = 'Reprezentace zprávy se nepodařilo uložit';
                    // false
                }
            } else {
                $zprava['stav_info'] = 'Zprávu se nepodařilo uložit';
            }

            return array(
                'source' => $mess,
                'epodatelna_id' => $epod_id,
                'isds_id' => $zprava,
                'zprava' => $popis
            );
        } catch (DibiException $e) {
            if (!empty($id_mess)) {
                $this->flashMessage('Chyba v DB: ' . $e->getMessage(), 'warning_ext');
                return array(
                    'source' => $mess,
                    'epodatelna_id' => $epod_id,
                    'isds_id' => $zprava,
                    'zprava' => $popis
                );
            } else {
                $this->flashMessage('Chyba v DB: ' . $e->getMessage(), 'warning_ext');
            }
        } catch (Exception $e) {
            // chyba v pripojeni k datove schrance
            $this->flashMessage('Chyba ISDS: ' . $e->getMessage(), 'warning_ext');
        }

        return false;
    }

    protected function createComponentSearchForm()
    {

        $hledat = !is_null($this->hledat) ? $this->hledat : '';

        $form = new Nette\Application\UI\Form();
        $form->addText('dotaz', 'Hledat:', 20, 100)
                ->setValue($hledat);
        
        $s3_hledat = UserSettings::get('spisovka_dokumenty_hledat');
        $s3_hledat = unserialize($s3_hledat);
        if (is_array($s3_hledat)) {
            $controlPrototype = $form['dotaz']->getControlPrototype();
            $controlPrototype->style(array('background-color' => '#ccffcc', 'border' => '1px #c0c0c0 solid'));
            $controlPrototype->title = "Aplikováno pokročilé vyhledávání. Pro detail klikněte na odkaz \"Pokročilé vyhledávání\". Zadáním hodnoty do tohoto pole, se pokročilé vyhledávání zruší a aplikuje se rychlé vyhledávání.";
        } else if (!empty($hledat)) {
            $controlPrototype = $form['dotaz']->getControlPrototype();
            //$controlPrototype->style(array('background-color' => '#ccffcc','border'=>'1px #c0c0c0 solid'));
            $controlPrototype->title = "Hledat lze dle věci, popisu, čísla jednacího a JID";
        } else {
            $form['dotaz']->getControlPrototype()->title = "Hledat lze dle věci, popisu, čísla jednacího a JID";
        }




        $form->addSubmit('hledat', 'Hledat')
                ->onClick[] = array($this, 'hledatSimpleClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = null;
        $renderer->wrappers['label']['container'] = null;
        $renderer->wrappers['control']['container'] = null;

        return $form;
    }

    public function hledatSimpleClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        UserSettings::set('spisovka_dokumenty_hledat', serialize($data['dotaz']));
        $this->redirect('default');
    }

    protected function createComponentFiltrForm()
    {
        // Typ pristupu na organizacni jednotku
        $filtr = !is_null($this->filtr) ? $this->filtr : 'vse';
        $select = array(
            'pridelene' => 'Přidělené',
            'kprevzeti' => 'K převzetí',
            'predane' => 'Předané',
            'nove' => 'Nové / nepředané',
            'kvyrizeni' => 'Vyřizuje se',
            'vyrizene' => 'Vyřízené',
            'pracoval' => 'Na kterých jsem kdy pracoval',
            'org_pracoval' => 'Na kterých pracovala moje o.j.',
            'vse' => 'Všechny',
            'Výpravna' => array(
                'doporucene' => 'Doporučené',
                'predane_k_odeslani' => 'K odeslání',
                'odeslane' => 'Odeslané',),
        );

        $filtr_bezvyrizenych = !is_null($this->filtr_bezvyrizenych) ? $this->filtr_bezvyrizenych
                    : false;
        $filtr_moje = !is_null($this->filtr_moje) ? $this->filtr_moje : false;

        $form = new Nette\Application\UI\Form();
        $form->addHidden('hidden')
                ->setValue(1);

        $control = $form->addSelect('filtr', 'Filtr:', $select)
                ->setValue($filtr);
        $control->getControlPrototype()->onchange("return document.forms['frm-filtrForm'].submit();");
        if ($this->zakaz_filtr)
            $control->setDisabled();

        $form->addCheckbox('bez_vyrizenych', 'Nezobrazovat vyřízené nebo archivované dokumenty')
                ->setValue($filtr_bezvyrizenych)
                ->getControlPrototype()->onchange("return document.forms['frm-filtrForm'].submit();");

        // Zde by se melo kontrolovat opravneni a podle nej pripadne Input vlozit jako Hidden pole
        // Pokud uzivatel neni v zadne org. jednotce,  na hodnote filtru "jen_moje" nezalezi
        $orgjednotka_id = Orgjednotka::dejOrgUzivatele();
        $user = $this->user;

        if (($orgjednotka_id === null || !$user->isAllowed('Dokument', 'cist_moje_oj')) && !$user->isAllowed('Dokument',
                        'cist_vse'))
            $control = $form->addHidden('jen_moje');
        else
            $control = $form->addCheckbox('jen_moje', 'Zobrazit jen dokumenty na mé jméno');
        $control->setValue($filtr_moje)
                ->getControlPrototype()->onchange("return document.forms['frm-filtrForm'].submit();");

        $form->addSubmit('go_filtr', 'Filtrovat');

        $form->onSuccess[] = array($this, 'filtrClicked');

        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = null;
        $renderer->wrappers['label']['container'] = null;
        $renderer->wrappers['control']['container'] = null;

        return $form;
    }

    public function filtrClicked(Nette\Application\UI\Form $form, $data)
    {
        $data2 = array('filtr' => $data['filtr'],
            'bez_vyrizenych' => $data['bez_vyrizenych'],
            'jen_moje' => $data['jen_moje']);

        UserSettings::set('spisovka_dokumenty_filtr', serialize($data2));

        $this->redirect('default');
    }

    protected function createComponentSeraditForm()
    {

        $select = array(
            'stav' => 'stavu dokumentu (vzestupně)',
            'stav_desc' => 'stavu dokumentu (sestupně)',
            'cj' => 'čísla jednacího (vzestupně)',
            'cj_desc' => 'čísla jednacího (sestupně)',
            'jid' => 'JID (vzestupně)',
            'jid_desc' => 'JID (sestupně)',
            'dvzniku' => 'data přijetí/vzniku (vzestupně)',
            'dvzniku_desc' => 'data přijetí/vzniku (sestupně)',
            'vec' => 'věci (vzestupně)',
            'vec_desc' => 'věci (sestupně)',
            'prideleno' => 'přidělené osoby (vzestupně)',
            'prideleno_desc' => 'přidělené osoby (sestupně)',
        );

        $form = new Nette\Application\UI\Form();
        $form->addSelect('seradit', 'Seřadit podle:', $select)
                ->getControlPrototype()->onchange("return document.forms['frm-seraditForm'].submit();");
        if (isset($this->seradit))
            $form['seradit']->setValue($this->seradit);

        $form->onSuccess[] = array($this, 'seraditFormSucceeded');

        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = null;
        $renderer->wrappers['label']['container'] = null;
        $renderer->wrappers['control']['container'] = null;

        return $form;
    }

    public function seraditFormSucceeded(Nette\Application\UI\Form $form, $form_data)
    {
        UserSettings::set('spisovka_dokumenty_seradit', $form_data['seradit']);
        $this->redirect('default');
    }

}
