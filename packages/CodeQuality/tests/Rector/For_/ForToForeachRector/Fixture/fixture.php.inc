<?php

namespace Rector\CodeQuality\Tests\Rector\For_\ForToForeachRector\Fixture;

class SomeClass
{
    public function run($tokens)
    {
        for ($i = 0, $c = count($tokens); $i < $c; ++$i) {
            if ($tokens[$i][0] === T_STRING && $tokens[$i][1] === 'fn') {
                $previousNonSpaceToken = $this->getPreviousNonSpaceToken($tokens, $i);
                if ($previousNonSpaceToken !== null && $previousNonSpaceToken[0] === T_OBJECT_OPERATOR) {
                    continue;
                }
                $tokens[$i][0] = self::T_FN;
            }
        }

        for ($i = 0, $c = count($tokens); $c > $i; $i++) {
        }
    }
}

?>
-----
<?php

namespace Rector\CodeQuality\Tests\Rector\For_\ForToForeachRector\Fixture;

class SomeClass
{
    public function run($tokens)
    {
        foreach ($tokens as $i => $token) {
            if ($tokens[$i][0] === T_STRING && $tokens[$i][1] === 'fn') {
                $previousNonSpaceToken = $this->getPreviousNonSpaceToken($tokens, $i);
                if ($previousNonSpaceToken !== null && $previousNonSpaceToken[0] === T_OBJECT_OPERATOR) {
                    continue;
                }
                $tokens[$i][0] = self::T_FN;
            }
        }

        foreach ($tokens as $i => $token) {
        }
    }
}

?>
