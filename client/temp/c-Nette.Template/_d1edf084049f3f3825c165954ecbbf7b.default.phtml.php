<?php //netteCache[01]000235a:2:{s:4:"time";s:21:"0.93085100 1291390147";s:9:"callbacks";a:1:{i:0;a:3:{i:0;a:2:{i:0;s:5:"Cache";i:1;s:9:"checkFile";}i:1;s:80:"C:\xampp\htdocs\spisovka1\trunk/app/../help/SpisovkaModule/Sestavy/default.phtml";i:2;i:1291390142;}}}?><?php
// file …/../help/SpisovkaModule/Sestavy/default.phtml
//

$_cb = LatteMacros::initRuntime($template, NULL, 'f3652a05ae'); unset($_extends);

if (SnippetHelper::$outputAllowed) {
?>
    <h2>Sestavy</h2>
    <p>
        Po kliknutí na tlačítko Sestavy se vám zobrazí všechny pevné a volitelné sestavy, které jsou vytvořeny.
        Rozdíl mezi pevnou a volitelnou sestavou je ten, že pevnou sestavu na rozdíl od té volitelné nelze již dále upravovat.
        Oba druhy sestav lze buď prohlížet nebo prohlížet a následně vytisknout.
    </p>
    <p>
        Poslední funkce je možnost vytvořit sestavu. K tomu slouží nápis Nová sestava. Pokud na tento nápis kliknete,
        budete přesměrováni na novou stránku, kde naleznete formulář sloužící k vytvoření sestavy.
        Tento formulář se dá rozdělit do tří sekci (Sestava, Filtr dokumentu a Adresáti/Odesílatelé)
        a obsahuje následující položky:
    </p>
    <dl>
        <dt>Sestava</dt><dd>Do této sekce se zadávají informační údaje týkající se nové sestavy.</dd>
        <dt>Název sestavy</dt><dd>Zde se zadává název pro sestavu.</dd>
        <dt>Popis sestavy</dt><dd>Sem můžete zadat charakterizující popis tvořené sestavy.</dd>
        <dt>Typ sestavy</dt><dd>Zde máte na výběr mezi volitelnou a pevnou sestavou.</dd>
        <dt>Filtrovat?</dt><dd>Pokud zaškrtnete tuto možnost, zadáváte tím, zda má být zobrazen výběr rozsahu podle pořadového čísla nebo data.</dd>
        <dt>Filtr dokumentu</dt><dd>Zde se zadávají údaje týkající se filtru dokumentů. Podle toho, co všechno vyplníte, bude filtr fungovat.
        <dt>Typ dokumentu</dt><dd>Zde máte po kliknutí na šipku směřující dolů na výběr hned z několika možností. Naleznete zde možnost příchozí, vlastní, odpověď, příchozí - doručeno emailem, příchozí - doručeno datovou schránkou, příchozí - doručeno datovým nosičem, příchozí - doručeno faxem, příchozí - doručeno v listinné podobě.</dd>
        <dt>Stav dokumentu</dt><dd>Zde máte na výběr opět z několika možností. Najdete zde možnost jakýkoliv stav, nový/rozpracovaný, přidělen/předán, vyřizuje se, vyřízen, vyřazen.</dd>
        <dt>Číslo jednací</dt><dd>Zde můžete zadat jednací číslo.</dd>
        <dt>Spisová značka</dt><dd>Toto je prostor pro zadání spisové značky.</dd>
        <dt>Věc</dt><dd>Zde můžete zadat předmět zprávy.</dd>
        <dt>Popis</dt><dd>Popis rozšiřuje informace o tom, o jaký dokument se jedná.</dd>
        <dt>Datum doručení/vzniku</dt><dd>Sem můžete zadat datum vzniku/doručení pomocí implementovaného kalendáře.</dd>
        <dt>Číslo jednací odesilatele</dt><dd>Pokud máte číslo jednací odesilatele, tak toto je místo, kde můžete toto pole vyplnit.</dd>
        <dt>Poznámka</dt><dd>Toto textové pole je určené pro různé poznámky.</dd>
        <dt>Počet listů</dt><dd>Do tohoto pole se udává počet listů.</dd>
        <dt>Počet listů příloh</dt><dd>Do tohoto pole se udává počet listů příloh.</dd>
        <dt>Způsob vyřízení</dt><dd>Zde máte opět na výběr z několika možností. Můžete zvolit možnost jakýkoliv typ dokumentu, vyřízení prvopisem, postoupení, vzetí na vědomí, úřední záznam, storno, jiný způsob.</dd>
        <dt>Datum vyřízení</dt><dd>Do tohoto pole můžete za pomocí implementovaného kalendáře zadat datum vyřízení.</dd>
        <dt>Datum odeslání</dt><dd>Do tohoto pole můžete za pomocí implementovaného kalendáře zadat datum odeslání.</dd>
        <dt>Spisový znak</dt><dd>V rozbalovacím menu máte na výběr zvolit jeden z vytvořených spisových znaků.</dd>
        <dt>Skartační znak</dt><dd>Zde opět volíte jednu z možností skartačních znaků.</dd>
        <dt>Skartační lhůta</dt><dd>Toto pole je určené pro udání skartační lhůty.</dd>
        <dt>Spouštěcí událost</dt><dd>Zde máte v rozbalovacím menu na výběr z mnoha položek, které lze označit jako spouštěcí událost pro skartační lhůtu.</dd>
        <dt>Počet listů</dt><dd>Do tohoto pole se udává počet listů.</dd>
        <dt>Počet listů příloh</dt><dd>Do tohoto pole se udává počet listů příloh.</dd>
        <dt>Uložení dokumentu</dt><dd>Zde se zadávají informace spojené s uložením dokumentu.</dd>
        <dt>Poznámka k vyřízení</dt><dd>Zde můžete zapsat poznámky související s vyřízením.</dd>
        <dt>Adresáti/Odesílatelé</dt><dd>Sem se zadávají informace o adresátovi/odesilateli.</dd>
        <dt>Typ subjektu</dt>
        <dt>Název subjektu</dt><dd>Zde se zadává název subjektu.</dd>
        <dt>IČ</dt><dd>Prostor pro IČ</dd>
        <dt>Ulice, č.p. a č.o. <dt><dd>Prostor pro adresu</dd>
        <dt>PSČ a město</dt><dd>Prostor pro PSČ a město</dd>
        <dt>Stát</dt><dd>Zde je na výběr jedna ze tři možností (v jakémkoliv státě, v ČR, v SK).</dd>
        <dt>Email</dt><dd>Prostor pro emailovou adresu.</dd>
        <dt>Telefon</dt><dd>Prostor pro telefonní spojení.</dd>
        <dt>ID datové schránky</dt><dd>Do tohoto pole se zadává ID datové schránky.</dd>
    </dl>
    <p>
        Jakmile máte všechny informace vyplněné, můžete kliknout na tlačítko Vytvořit. V případě, že jste si vytvoření
        sestavy rozmysleli, klikněte na tlačítko Zrušit a všechna zadaná data budou ztracena.
    </p>
<?php
}
