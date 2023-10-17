<?php

declare(strict_types=1);

namespace Ecs\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\Whitespace\CommentToPhpdocFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class AlignCommentsFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Comments should be aligned with code and have a space after "//".',
            [],
            null,
            'Ensure comments are aligned with the code and have a space after "//".'
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return true;
    }

    public function isRisky(): bool
    {
        return false;
    }

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
		foreach ($tokens as $index => $token) {
            if ($token->isGivenKind([\T_COMMENT])) {
                $content = $token->getContent();
                // $newContent = preg_replace($pattern, $replacement, $content);
                $newContent = preg_replace('/\/\/\s+/', '// ', $content);
                $tokens[$index] = new Token([T_COMMENT, $newContent]);
            }
        }
    }
}

