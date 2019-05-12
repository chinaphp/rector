<?php declare(strict_types=1);

namespace Rector\CodeQuality\Rector\For_;

use Doctrine\Common\Inflector\Inflector;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class ForToForeachRector extends AbstractRector
{
    /**
     * @var string|null
     */
    private $keyValueName;

    /**
     * @var string|null
     */
    private $countValueName;

    /**
     * @var Node\Expr|null
     */
    private $iteratedExpr;

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Change for() to foreach() where useful', [
            new CodeSample(
                <<<'CODE_SAMPLE'
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
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run($tokens)
    {
        foreach ($tokens as $i => $token) {
            if ($token[0] === T_STRING && $token[1] === 'fn') {
                $previousNonSpaceToken = $this->getPreviousNonSpaceToken($tokens, $i);
                if ($previousNonSpaceToken !== null && $previousNonSpaceToken[0] === T_OBJECT_OPERATOR) {
                    continue;
                }
                $tokens[$i][0] = self::T_FN;
            }
        }
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [For_::class];
    }

    /**
     * @param For_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $this->reset();

        $this->matchInit((array) $node->init);

        if (! $this->isConditionMatch((array) $node->cond)) {
            return null;
        }

        if (! $this->isLoopMatch((array) $node->loop)) {
            return null;
        }

        if ($this->iteratedExpr === null || $this->keyValueName === null) {
            return null;
        }

        $iteratedVariable = $this->getName($this->iteratedExpr);
        if ($iteratedVariable === null) {
            return null;
        }

        $iteratedVariableSingle = Inflector::singularize($iteratedVariable);

        $foreach = new Foreach_($this->iteratedExpr, new Node\Expr\Variable($iteratedVariableSingle));
        $foreach->stmts = $node->stmts;

        if ($this->keyValueName === null) {
            return null;
        }

        $foreach->keyVar = new Node\Expr\Variable($this->keyValueName);

        return $foreach;
    }

    /**
     * @param Node\Expr[] $initExprs
     */
    private function matchInit(array $initExprs): void
    {
        foreach ($initExprs as $initExpr) {
            if ($initExpr instanceof Node\Expr\Assign) {
                if ($this->isValue($initExpr->expr, 0)) {
                    $this->keyValueName = $this->getName($initExpr->var);
                }

                if ($initExpr->expr instanceof Node\Expr\FuncCall && $this->isName($initExpr->expr, 'count')) {
                    $this->countValueName = $this->getName($initExpr->var);
                    $this->iteratedExpr = $initExpr->expr->args[0]->value;
                }
            }
        }
    }

    /**
     * @param Node\Expr[] $condExprs
     */
    private function isConditionMatch(array $condExprs): bool
    {
        if (count($condExprs) !== 1) {
            return false;
        }

        if ($this->keyValueName === null) {
            return false;
        }

        if ($this->countValueName !== null) {
            return $this->isSmallerOrGreater($condExprs, $this->keyValueName, $this->countValueName);
        }

        // count($values)
        if ($condExprs[0]->right instanceof Node\Expr\FuncCall) {
            if ($this->isName($condExprs[0]->right, 'count')) {
                /** @var Node\Expr\FuncCall $countFuncCall */
                $countFuncCall = $condExprs[0]->right;
                $this->iteratedExpr = $countFuncCall->args[0]->value;

                return true;
            }
        }

        return false;
    }

    /**
     * @param Node\Expr[] $loopExprs
     * $param
     */
    private function isLoopMatch(array $loopExprs): bool
    {
        if (count($loopExprs) !== 1) {
            return false;
        }

        if ($this->keyValueName === null) {
            return false;
        }

        if ($loopExprs[0] instanceof Node\Expr\PreInc || $loopExprs[0] instanceof Node\Expr\PostInc) {
            return $this->isName($loopExprs[0]->var, $this->keyValueName);
        }

        return false;
    }

    private function reset(): void
    {
        $this->keyValueName = null;
        $this->countValueName = null;
        $this->iteratedExpr = null;
    }

    /**
     * @param Node\Expr[] $condExprs
     */
    private function isSmallerOrGreater(array $condExprs, string $keyValueName, string $countValueName): bool
    {
        // $i < $count
        if ($condExprs[0] instanceof Smaller) {
            if (! $this->isName($condExprs[0]->left, $keyValueName)) {
                return false;
            }

            return $this->isName($condExprs[0]->right, $countValueName);
        }

        // $i > $count
        if ($condExprs[0] instanceof Greater) {
            if (! $this->isName($condExprs[0]->left, $countValueName)) {
                return false;
            }

            return $this->isName($condExprs[0]->right, $keyValueName);
        }

        return false;
    }
}
