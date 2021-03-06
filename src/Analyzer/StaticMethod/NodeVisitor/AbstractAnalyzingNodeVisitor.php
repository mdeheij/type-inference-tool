<?php
declare(strict_types=1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use PhpParser\NodeVisitorAbstract;

/**
 * Allows AnalyzedFunction analyzers to store and retrieve
 * data to and from an AnalyzedFunctionCollection.
 */
abstract class AbstractAnalyzingNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var AnalyzedFunctionCollection
     */
    private $analyzed_function_collection;

    /**
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     */
    public function __construct(AnalyzedFunctionCollection $analyzed_function_collection)
    {
        $this->analyzed_function_collection = $analyzed_function_collection;
    }

    /**
     * @return AnalyzedFunctionCollection
     */
    protected function getAnalyzedFunctionCollection(): AnalyzedFunctionCollection
    {
        return $this->analyzed_function_collection;
    }
}
