<?php

function preloadedFunction() {

}

echo 'Result in preload script'.PHP_EOL;
flush();
detectOpcachePreload();

?>