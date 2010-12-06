<?php //netteCache[01]000237a:2:{s:4:"time";s:21:"0.37725100 1291370740";s:9:"callbacks";a:1:{i:0;a:3:{i:0;a:2:{i:0;s:5:"Cache";i:1;s:9:"checkFile";}i:1;s:82:"C:\xampp\htdocs\spisovka1\trunk/app/templates/InstallModule/Default/evidence.phtml";i:2;i:1291364704;}}}?><?php
// file …/templates/InstallModule/Default/evidence.phtml
//

$_cb = LatteMacros::initRuntime($template, NULL, '8b3baad19f'); unset($_extends);


//
// block title
//
if (!function_exists($_cb->blocks['title'][] = '_cbbb4db007617_title')) { function _cbbb4db007617_title() { extract(func_get_arg(0))
?>Instalace - nastavení evidence<?php
}}


//
// block menu
//
if (!function_exists($_cb->blocks['menu'][] = '_cbb3a5b90460c_menu')) { function _cbb3a5b90460c_menu() { extract(func_get_arg(0))
?>
<a href="<?php echo TemplateHelpers::escapeHtml($control->link(":Install:Default:uvod")) ?>">Úvod</a> >
<a href="<?php echo TemplateHelpers::escapeHtml($control->link(":Install:Default:kontrola")) ?>">Kontrola</a> >
<a href="<?php echo TemplateHelpers::escapeHtml($control->link(":Install:Default:databaze")) ?>">Nahrání databáze</a> >
<a href="<?php echo TemplateHelpers::escapeHtml($control->link(":Install:Default:urad")) ?>">Nastavení klienta</a> >
<strong>Nastavení evidence</strong> >
<span>Nastavení správce</span> >
<span>Konec</span>
<?php
}}


//
// block content
//
if (!function_exists($_cb->blocks['content'][] = '_cbbcb1336108d_content')) { function _cbbcb1336108d_content() { extract(func_get_arg(0))
?>
<h1>Instalace - nastavení evidence</h1>

<p>
V tomto kroku vyberte typ evidence a podobu čísla jednacího. V případě navázání na předchozí evidenci,
můžete zadat počáteční pořadové číslo, které bude pokračovat z původní evidence.
</p>
<p>
Každý úřad volí vlastní podobu čísla jednacího, proto je zde možnost si číslo jednací přizpůsobit podle
spisového řádu úřadu. V tabulce jsou uvedeny možné volby, které lze zakomponovat do čísla jednacího.
Držte se přesného zápisu masky. Přípustné masky jsou pouze ty, které jsou uvedeny v tabulce.
Jakékoli další masky nejsou k dispozici. Jakékoli další znaky, které nejsou masky, jsou považovány
za neměnné.
</p>

<p style="color: red; font-weight: bold;">
Důležité upozornění! Důkladně zvažte volbu evidence. Tato volba se nastavuje pouze zde a po uvedení
do provozu již nelze tuto volbu změnit.
</p>

    <div class="detail_blok">
<?php $control->getWidget("nastaveniCJForm")->render() ?>
        <dl class="detail_item">
            <dt>Masky:</dt>
            <dd class="normal">
                <table>
                    <tr>
                        <th><strong>maska</strong></th>
                        <th>ukázka</th>
                        <th>popis</th>
                    </tr>
                    <tr>
                        <td><strong>&#123;evidence&#125;</strong></td>
                        <td>denik</td>
                        <td>evidence / podací deník</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;poradove_cislo&#125;</strong></td>
                        <td>64</td>
                        <td>pořadové číslo evidence</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;poradove_cislo|n&#125;</strong></td>
                        <td>000064</td>
                        <td>pořadové číslo evidence s počátečními nulami (ukázka &#123;poradove_cislo|6&#125;)</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;rok&#125;</strong></td>
                        <td><?php echo TemplateHelpers::escapeHtml(date("Y")) ?></td>
                        <td>rok</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;urad&#125;</strong></td>
                        <td>MEVs</td>
                        <td>zkratka úřadu</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;urad_poradi&#125;</strong></td>
                        <td>64</td>
                        <td>pořadové číslo úřadu</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;urad_poradi|n&#125;</strong></td>
                        <td>064</td>
                        <td>pořadové číslo úřadu s počátečními nulami (ukázka &#123;urad_poradi|3&#125;)</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;org&#125;</strong></td>
                        <td>UIT</td>
                        <td>zkratka organizační jednotky / útvaru</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;org_id&#125;</strong></td>
                        <td>1</td>
                        <td>vnitřní identifikátor organizační jednotky</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;org_id|n&#125;</strong></td>
                        <td>001</td>
                        <td>vnitřní identifikátor organizační jednotky s počátečními nulami (ukázka &#123;org_id|3&#125;)</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;org_poradi&#125;</strong></td>
                        <td>64</td>
                        <td>pořadové číslo organizační jednotky</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;org_poradi|n&#125;</strong></td>
                        <td>00064</td>
                        <td>pořadové číslo organizační jednotky s počátečními nulami (ukázka &#123;org_poradi|6&#125;)</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;user_id&#125;</strong></td>
                        <td>21</td>
                        <td>vnitřní identifikátor uživatele</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;user_id|n&#125;</strong></td>
                        <td>21</td>
                        <td>vnitřní identifikátor uživatele s počátečními nulami (ukázka &#123;user_id|1&#125;)</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;user&#125;</strong></td>
                        <td>novak</td>
                        <td>přihlašovací jméno uživatele</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;prijmeni&#125;</strong></td>
                        <td>novak</td>
                        <td>příjmení uživatele</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;user_poradi&#125;</strong></td>
                        <td>5</td>
                        <td>pořadové číslo uživatele</td>
                    </tr>
                    <tr>
                        <td><strong>&#123;user_poradi|n&#125;</strong></td>
                        <td>000005</td>
                        <td>pořadové číslo uživatele s počátečními nulami (ukázka &#123;user_poradi|6&#125;)</td>
                    </tr>
                </table>
            </dd>
        </dl>
    </div><?php
}}

//
// end of blocks
//

if ($_cb->extends) { ob_start(); }

if (SnippetHelper::$outputAllowed) {
if (!$_cb->extends) { call_user_func(reset($_cb->blocks['title']), get_defined_vars()); } ?>

<?php if (!$_cb->extends) { call_user_func(reset($_cb->blocks['menu']), get_defined_vars()); }  if (!$_cb->extends) { call_user_func(reset($_cb->blocks['content']), get_defined_vars()); }  
}

if ($_cb->extends) { ob_end_clean(); LatteMacros::includeTemplate($_cb->extends, get_defined_vars(), $template)->render(); }
