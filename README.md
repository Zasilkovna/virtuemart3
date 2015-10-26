<h1>Modul pro VirtueMart 3, úprava pro modul One Page Checkout od VirtuePlanet</h1>
<h2>Instalace</h2>
<ol style="color: black; ">
  <li><a href="https://github.com/Zasilkovna/virtuemart3/archive/VirtuePlanetOPC.zip">Stáhnout soubor modulu »</a></li>
  <li>Modul vyžaduje minimálně verzi <b>Joomla 3.0</b> a <b>VirtueMart 3.0.x</b>. Máte-li starší, napište nám prosím používanou verzi a adresu e-shopu na <a href="mailto:technicka.podpora@zasilkovna.cz">technicka.podpora@zasilkovna.cz</a>.</li>
  <li>
    Přihlašte se do administrace Joomly, otevřete nabídku Extensions -> Extension manager a nahrajte nainstalujte modul<br><br>
    <a href="https://cloud.githubusercontent.com/assets/11771520/9033047/5410b934-39c4-11e5-8335-ba934bc9cf7c.png"><img src="https://cloud.githubusercontent.com/assets/11771520/9033047/5410b934-39c4-11e5-8335-ba934bc9cf7c.png"></a><br><br>
  </li>
  <li>
    Mělo by se zobrazit hlášení o úspěšné instalaci. Nyní je potřeba modul povolit.<br><br>
    <a href="https://cloud.githubusercontent.com/assets/11771520/9033152/11e93936-39c5-11e5-976c-d65d15f0b644.png"><img src="https://cloud.githubusercontent.com/assets/11771520/9033152/11e93936-39c5-11e5-976c-d65d15f0b644.png"></a>
    <br><br>
  </li>
  <li>
    Nastavení hesla API, názvu obchodu, dobírky a další se provede na stránce <b>Components – VirtueMart – Configuration – ZASILKOVNA</b> v panelu Config. Váše heslo API najdete ve své klientské sekci, pod <strong><em>Můj účet</em></strong><br><br>
    <a href="https://cloud.githubusercontent.com/assets/11771520/9033470/8035128c-39c7-11e5-81c1-f88fa906f5ad.png"><img src="https://cloud.githubusercontent.com/assets/11771520/9033470/8035128c-39c7-11e5-81c1-f88fa906f5ad.png"></a><br><br>
  </li>  
  <li>
    Po nastavení hesla API je potřeba přidat dopravní metody. V <b>Components – VirtueMart – Shop – Shipment Methods</b> přidejte novou dopravní metodu a vyberte <b>Shipment method: Zasilkovna VM3</b><br><br>
    <a href="https://cloud.githubusercontent.com/assets/11771520/9033592/f5afc4b6-39c8-11e5-8415-7778fd07601b.png"><img src="https://cloud.githubusercontent.com/assets/11771520/9033592/f5afc4b6-39c8-11e5-8415-7778fd07601b.png"></a><br><br>
  </li>
  <li>
    U dopravní metody je ještě potřeba <b>nastavit cenu, daň a cílovou zemi</b> (pokud chcete povolit všechny země, žádnou nevybírejte). Nastavení provedete rozkliknutím dopravní metody v panelu <b>Configuration</b><br><br>
    <a href="https://cloud.githubusercontent.com/assets/11771520/9033594/f781f296-39c8-11e5-837d-d929255e1653.png"><img src="https://cloud.githubusercontent.com/assets/11771520/9033594/f781f296-39c8-11e5-837d-d929255e1653.png"></a><br><br>
  </li>
  <li>
    Nyní by měla být zásilkovna nabízena jako další možnost dopravy.   <br><br>
    <a href="https://cloud.githubusercontent.com/assets/11771520/9033596/f96466b6-39c8-11e5-91c2-deffb26fa703.png"><img src="https://cloud.githubusercontent.com/assets/11771520/9033596/f96466b6-39c8-11e5-91c2-deffb26fa703.png"></a><br><br>
  </li>
  <li>
    Pokud si přejete <b>omezit některé kombinace doprava-platba</b>, postupujte dle návodu v nastavení modulu (Components - VirtueMart - Configuration - ZASILKOVNA) panel Config dole. Poté můžete v tabulce zaškrtat povolené kombinace.<br><br>
  </li>  
  <li>
    Dále až budete mít nějaké objednávky se způsobem dopravy Zásilkovna, můžete je automaticky podat do systému Zásilkovny, vytisknout štítky nebo exportovat do CSV. To vše se provede v nastavení modulu (Components - VirtueMart - Configuration - ZASILKOVNA) panelu <b>EXPORT</b><br><br>
    <a href="https://cloud.githubusercontent.com/assets/11771520/9033598/fb3b509e-39c8-11e5-952a-5f400c8ba3d4.png"><img src="https://cloud.githubusercontent.com/assets/11771520/9033598/fb3b509e-39c8-11e5-952a-5f400c8ba3d4.png"></a><br><br>
  </li>
</ol>
<h2>Informace o modulu</h2>
<p>Podporované verze:</p>
<ul>
  <li>VirtueMart 3.0.x a novější</li>
  <li>Joomla! 3.0 a novější</li>
  <li>Při problému s použitím v jiné verzi nás kontaktujte na adrese <a href="mailto:technicka.podpora@zasilkovna.cz">technicka.podpora@zasilkovna.cz</a></li>
</ul>
<p>Poskytované funkce:</p>
<ul>
  <li>Instalace typu dopravního modulu Zásilkovna
    <ul>
      <li>možnost rozlišení ceny dle cílové země</li>
      <li>volba typu zobrazení stejná jako v <a href="http://www.zasilkovna.cz/pristup-k-pobockam/pruvodce">průvodci vložením poboček (JS API)</a></li>
      <li>vybraná pobočka se zobrazuje v detailu objednávky v uživatelské (front-office) i administrátorské (back-office) sekci</li>
    </ul>
  </li>
  <li>Možnost exportu souboru s objednávkami
    <ul>
      <li>možnost označit objednávky, export CSV souboru pro hromadné podání zásilek</li>
      <li>vyznačení již exportovaných objednávek</li>
      <li>automatické a manuální označení dobírek</li>
    </ul>
  </li>
  <li>Možnost přímého podání do systému Zásilkovna a tisku štítků</li>  
  <li>Možnost zakázání některých kombinací doprava-platba</li>  
</ul>
