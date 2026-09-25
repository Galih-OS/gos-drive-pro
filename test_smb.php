<?php

$path =
    '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\';

echo '<pre>';

echo "Path:\n";
echo $path . "\n\n";


echo "Folder ada: ";

var_dump(
    is_dir($path)
);


echo "\nBisa ditulis: ";

var_dump(
    is_writable($path)
);


echo "\nPHP User:\n";

echo get_current_user();


echo '</pre>';