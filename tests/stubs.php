<?php

/**
 * Stand-ins for the classes Grido takes from the Ciki skeleton (libs/Custom) - the tests run without the skeleton.
 */

namespace Ciki\Forms\Controls {
    if (!class_exists(SelectBox::class)) {
        class SelectBox extends \Nette\Forms\Controls\SelectBox
        {
        }
    }

    if (!class_exists(MultiSelectBox::class)) {
        class MultiSelectBox extends \Nette\Forms\Controls\MultiSelectBox
        {
        }
    }
}

namespace Ciki\Grido {
    if (!class_exists(ColumnNumber::class)) {
        class ColumnNumber extends \Grido\Components\Columns\Number
        {
            public function getCalculateSum(): bool
            {
                return false;
            }
        }
    }
}
