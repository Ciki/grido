<?php

/**
 * Stand-ins for the classes Grido takes from the Ciki skeleton (libs/Custom) and from the optional
 * contributte/pdf-response - the tests and PHPStan run without them.
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

namespace Contributte\PdfResponse {
    if (!class_exists(PdfResponse::class)) {
        class PdfResponse
        {
            public string $pageFormat = 'A4';

            public function __construct(mixed $source = null)
            {
            }

            public function __toString(): string
            {
                return '';
            }
        }
    }
}
