<?php

use Finller\LaravelMedia\Tests\Models\Test;

it('retrieve the generated conversion key', function () {
    $model = (new Test())->save();
});
