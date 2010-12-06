<?php //netteCache[01]000235a:2:{s:4:"time";s:21:"0.62517700 1291392576";s:9:"callbacks";a:1:{i:0;a:3:{i:0;a:2:{i:0;s:5:"Cache";i:1;s:9:"checkFile";}i:1;s:80:"C:\xampp\htdocs\spisovka1\trunk/app/../help/AdminModule/Epodatelna/default.phtml";i:2;i:1291392571;}}}?><?php
// file …/../help/AdminModule/Epodatelna/default.phtml
//

$_cb = LatteMacros::initRuntime($template, NULL, 'f2277189b7'); unset($_extends);

if (SnippetHelper::$outputAllowed) {
?>
    <h2>Nastavení e-podatelny</h2>
    <p>
        Po kliknutí na tlačítko E-podatelna se vám zobrazí soupis datových schránek, emailových schránek, odesílání přes email a kvalifikované certifikační autority. Kromě soupisu však máte možnost datové schránky a emaily nastavovat.
        Nejprve se podíváme na nastavení datové schránky. K tomu je potřeba najet a kliknout na nápis, který naleznete pod nadpisem Datové schránky. Poté se vám zobrazí informace o nastavení účtu. Nyní je potřeba kliknout na nápis Upravit.
        V této sekci můžete upravit následující údaje:
    </p>
    <dl>
        <dt>Název účtu</dt><dd>Tato položka je povinná.</dd>
        <dt>Aktivní účet</dt><dd>Jedná se o zaškrtávací pole určující, zda je účet aktivní.</dd>
        <dt>Typ přihlášení</dt><dd>Zde v rozbalovacím poli vyberte jednu z možností. Na výběr máte buď Základní (jméno a heslo, Spisovka (certifikát) nebo Hostovaná spisovka (certifikát + jméno a heslo)</dd>
        <dt>Přihlašovací jméno od ISDS</dt><dd>Toto je druhá povinná položka, kterou musíte zadat.</dd>
        <dt>Přihlašovací heslo od ISDS</dt><dd>Třetí a poslední povinná položka je heslo od ISDS.</dd>
        <dt>Cesta k certifikátu (formát X.509)</dt><dd>Zde se nachází tlačítko Procházet, na které když kliknete, můžete zadat cestu k vašemu certifikátu.</dd>
        <dt>Režim</dt><dd>Zde po rozkliknutí rozbalovacího pole můžete vybrat jednu ze dvou  možností a to konkrétně mezi Reálným provozem (mojedatovaschranka.cz) nebo pomocí Testovacího režimu (czebox.cz).</dd>
        <dt>Podatelna pro příjem</dt>
    </dl>
    <p>
        Jakmile máte vše vyplněné, najeďte a klikněte na tlačítko Uložit. Pakliže jste si úpravy rozmysleli, klikněte levým tlačítkem myši na tlačítko Zrušit.
        Pokud chcete upravit emailovou schránku, najeďte na nápis pod nadpisem Emailové schránky a klikněte na něj.
        Zobrazí se vám souhrn informací týkající se emailové schránky. Pro editaci těchto údajů musíte kliknout na nápis Upravit
        Opět je zde několik položek, které můžete upravovat.
        Zde je jich stručný popis:
    </p>
    <dl>
    <dt>Název účtu</dt><dd>Opět se jedná o první povinnou položku ve formuláři.</dd>
    <dt>Aktivní účet</dt><dd>Jedná se o zaškrtávací pole určující, zda je účet aktivní.</dd>
    <dt>Protokol</dt><dd>Druhou povinnou položkou je protokol. Na výběr máte hned z několika možnosti (POP3, POP3-SSL, IMAP, IMAP-SSL a NNTP).</dd>
    <dt>Adresa serveru</dt><dd>Třetí povinné textové pole, kde je nutné zadat adresu hostitelského serveru.</dd>
    <dt>Port</dt><dd>Čtvrtá povinná položka tohoto formuláře.</dd>
    <dt>Složka</dt>
    <dt>Přihlašovací jméno</dt><dd>Jedná se o pátou povinnou položku. Jak již napovídá název, musíte zadat přihlašovací jméno k vašemu emailu.</dd>
    <dt>Přihlašovací heslo</dt><dd>Šestou poslední položkou je heslo k vašemu emailu.</dd>
    <dt>Podatelna pro příjem</dt>
    <dt>Přijímat pouze emaily s připojeným e-podpisem</dt><dd>Pokud je toto zaškrtávací pole zaškrtnuto, pak budou do E-podatelny přijímány jen ty emaily, které budou obsahovat elektronický podpis.</dd>
    <dt>Přijímat pouze emaily s ověřeným kvalifikovaným podpisem/značkou</dt><dd>Opět se jedná o zaškrtávací pole, které když zaškrtnete budou vám do E-podatelny přicházet jen ty emaily, které budou opatřeny ověřeným kvalifikovaným podpisem či značkou.</dd>
    </dl>
    <p>
    Jakmile máte všechny údaje vyplněny, můžete kliknout na tlačítko Uložit. Pokud chcete úpravu údajů přerušit, klikněte na tlačítko Zrušit.
    Poslední možností úpravy, kterou můžete provádět pod tlačítkem E-podatelna, je nastavení emailu pro odesílání přes email. Stačí jen kliknout na nápis pod nadpisem Odesílání přes email a následně kliknout na nápis Upravit, které najdete pod soupisem informací o nastavení účtu.
    Jakmile se otevře nová obrazovka, můžete upravovat následující položky:
    </p>
    <dl>
        <dt>Název účtu</dt><dd>Je jedinou povinnou položkou tohoto formuláře.</dd>
        <dt>Aktivní účet</dt><dd>Jedná se o zaškrtávací pole určující, zda je účet aktivní.</dd>
        <dt>Jak odesílat</dt><dd>Zde máte možnost vybrat buď možnost klasicky bez kvalifikovaného podpisu/značky nebo s kvalifikovaným podpisem/značkou.</dd>
        <dt>Emailová adresa odesilatele</dt><dd>Sem zadáte vaši emailovou adresu</dd>
        <dt>Cesta k certifikátu</dt><dd>Po kliknutí na tlačítko Procházet budete moci zadat cestu k vašemu certifikátu.</dd>
        <dt>Cesta k privátnímu klíči</dt><dd>U této položky se opět nachází tlačítko Procházet, na které když kliknete, budete moci zadat cestu k vašemu privátnímu klíči.</dd>
        <dt>Heslo k klíči certifikátu: Do tohoto pole zadáváte heslo ke klíči vašeho certifikátu.</dt>
    </dl>
    <p>
    Platí to samé jako v předchozích případech - pokud máte vše vyplněno podle vašich představ, stačí kliknout na tlačítko Uložit. V opačném případě, pokud chcete zrušit upravování emailové schránky pro odesílání přes email,
    tak klikněte na tlačítko Zrušit.
    </p><?php
}
