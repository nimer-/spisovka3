<?php

namespace Spisovka;

use Nette;

class Admin_SpisyPresenter extends SpisyPresenter
{

    public $hledat;

    public function startup()
    {
        $client_config = GlobalVariables::get('client_config');
        $this->template->Typ_evidence = $client_config->cislo_jednaci->typ_evidence;
        $this->template->Oddelovac_poradi = $client_config->cislo_jednaci->oddelovac;
        parent::startup();
    }

    public function renderDefault()
    {
        $this->redirect('seznam');
    }

    public function renderSeznam($hledat = null)
    {
        $Spisy = new SpisModel();
        $result = $Spisy->seznamRychly();
        $result->setRowClass(null);
        $this->template->spisy = $result->fetchAll();

        /*
          $this->hledat = $hledat;

          $Spisy = new Spis();

          $args = null;
          if (!empty($hledat)) {
          $args = array('where' => array(array("tb.nazev LIKE %s", '%' . $hledat . '%')));
          }

          $client_config = GlobalVariables::get('client_config');
          $vp = new Components\VisualPaginator($this, 'vp', $this->getHttpRequest());
          $paginator = $vp->getPaginator();
          $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek
          : 20;

          $result = $Spisy->seznam($args);
          $paginator->itemCount = count($result);

          // Volba vystupu - web/tisk/pdf
          $tisk = $this->getParameter('print');
          $pdf = $this->getParameter('pdfprint');
          if ($tisk || $pdf) {
          $seznam = $result->fetchAll();
          if (count($seznam) > 0) {
          $spis_ids = array();
          foreach ($seznam as $spis) {
          $spis_ids[] = $spis->id;
          }
          $this->template->seznam_dokumentu = $Spisy->seznamDokumentu($spis_ids);
          } else {
          $this->template->seznam_dokumentu = array();
          }
          $this->setView('print');
          } else {
          $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
          }

          $this->template->seznam = $seznam;

         * 
         */
    }

    public function renderDetail($id, $upravit)
    {
        $spis = new Spis($id);
        $this->template->Spis = $spis;

        $this->template->FormUpravit = $upravit;
        $this->template->SpisyNad = null; // $Spisy->seznam_nad($spis_id,1);
        $this->template->SpisyPod = null; //$Spisy->seznam_pod($spis_id,1);

        $this->template->SpisZnak_nazev = "";
        if (!empty($spis->spisovy_znak_id)) {
            $SpisovyZnak = new SpisovyZnak();
            $sz = $SpisovyZnak->select(["[id] = $spis->spisovy_znak_id"])->fetch();
            $this->template->SpisZnak_nazev = $sz->nazev;
        }

        $this->template->seznam = $spis->getDocumentsPlus();

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk || $pdf) {
            $this->setView('printdetail');
        }
    }

    public function renderExport()
    {
        if ($this->getHttpRequest()->isMethod('POST')) {
            // Exportovani
            $post_data = $this->getHttpRequest()->getPost();

            $Spis = new SpisModel();
            $args = null;
            if ($post_data['export_co'] == 2) {
                // pouze aktivni
                $args['where'] = array(array('stav=1'));
            }

            $seznam = $Spis->seznam($args)->fetchAll();

            if ($seznam) {
                if ($post_data['export_do'] == "csv") {
                    // export do CSV
                    $ignore_cols = array("date_created", "user_created", "date_modified", "user_modified",
                        "sekvence_string");
                    $export_data = CsvExport::csv(
                                    $seznam, $ignore_cols, $post_data['csv_code'],
                                    $post_data['csv_radek'], $post_data['csv_sloupce'],
                                    $post_data['csv_hodnoty']);

                    //echo "<pre>"; echo $export_data; echo "</pre>"; exit;

                    $httpResponse = $this->getHttpResponse();
                    $httpResponse->setContentType('application/octetstream');
                    $httpResponse->setHeader('Content-Description', 'File Transfer');
                    $httpResponse->setHeader('Content-Disposition',
                            'attachment; filename="export_spisu.csv"');
                    $httpResponse->setHeader('Content-Transfer-Encoding', 'binary');
                    $httpResponse->setHeader('Expires', '0');
                    $httpResponse->setHeader('Cache-Control',
                            'must-revalidate, post-check=0, pre-check=0');
                    $httpResponse->setHeader('Pragma', 'public');
                    $httpResponse->setHeader('Content-Length', strlen($export_data));
                    echo $export_data;
                    exit;
                }
            } else {
                $this->flashMessage('Nebyly nalezany žádné data k exportu!', 'warning');
            }
        }
    }

    protected function createComponentSearchForm()
    {
        $hledat = $this->hledat ?: '';

        $form = new Nette\Application\UI\Form();
        $form->addText('dotaz', 'Hledat:', 20, 100)
                ->setValue($hledat);
        $form['dotaz']->getControlPrototype()->title = "Hledat lze dle názvu spisu";

        $form->addSubmit('hledat', 'Hledat')
                ->onClick[] = array($this, 'hledatSimpleClicked');

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

        $this->redirect('this', array('hledat' => $data['dotaz']));
    }

    public function actionRebuild()
    {
        $m = new SpisModel();
        $m->rebuildIndex();
        $this->flashMessage('Operace proběhla úspěšně.');
        $this->redirect('seznam');
    }

    public function actionSmazat($id)
    {
        $spis = new Spis($id);
        $spis->delete();
        $this->flashMessage('Složka byla smazána.');
        $this->redirect('seznam');
    }

}
