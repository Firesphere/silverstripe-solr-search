<?php

function getShortFieldName($name)
{
    $name = explode('\\', $name);

    return end($name);
}
