<?php
declare(strict_types = 1);
/**
 * @copyright 2017 Hostnet B.V.
 */
namespace Hostnet\Component\TypeInference\Analyzer\StaticMethod;

use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunction;
use Hostnet\Component\TypeInference\Analyzer\Data\AnalyzedFunctionCollection;
use Hostnet\Component\TypeInference\Analyzer\FunctionAnalyzerInterface;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\AstConverter\PhpAstConverter;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor\DocblockNodeVisitor;
use Hostnet\Component\TypeInference\Analyzer\StaticMethod\NodeVisitor\FunctionNodeVisitor;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Uses static analysis to collect argument- and return types from functions calls
 * in a target project.
 */
final class StaticAnalyzer implements FunctionAnalyzerInterface
{
    /**
     * Prefix used for logs outputted by this class. Name used
     * by stopwatch for this class.
     */
    const TIMER_LOG_NAME = 'STATIC_ANALYSIS';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Collects {@link AnalyzedFunction} by using static analysis.
     *
     * @param string $target_project
     * @return AnalyzedFunction[]
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function collectAnalyzedFunctions(string $target_project): array
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start(self::TIMER_LOG_NAME);
        $this->logger->info(self::TIMER_LOG_NAME . ': Started static analysis');

        $analyzed_functions = new AnalyzedFunctionCollection();

        $this->analyseMethods($analyzed_functions, $target_project);
        $this->analyseDocblocks($analyzed_functions, $target_project);

        $this->logger->info(self::TIMER_LOG_NAME . ': Finished static analysis ({time}s)', [
            'time' => round($stopwatch->stop(self::TIMER_LOG_NAME)->getDuration() / 1000, 2)
        ]);

        return $analyzed_functions->getAll();
    }

    /**
     * Collects defined type hints from source files. These type hints are used during
     * type inference analysis. This means that inferred type hints are also based on
     * docblocks.
     *
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param string $target_project
     */
    private function analyseDocblocks(AnalyzedFunctionCollection $analyzed_function_collection, string $target_project)
    {
        $this->analyseSourceAsts(
            $target_project,
            function (array $ast) use ($analyzed_function_collection, $target_project) {
                $docblock_visitor = new DocblockNodeVisitor(
                    $analyzed_function_collection,
                    $this->retrieveSourceFiles($target_project)
                );
                $this->travelTree($ast, $docblock_visitor);
            }
        );
    }

    /**
     * Collects function data by traversing AST's of all source files. These
     * AnalyzedFunctions are used to infer parameter- and return types.
     *
     * @param AnalyzedFunctionCollection $analyzed_function_collection
     * @param string $target_project
     */
    private function analyseMethods(AnalyzedFunctionCollection $analyzed_function_collection, string $target_project)
    {
        $this->analyseSourceAsts(
            $target_project,
            function (array $ast, SplFileInfo $file) use ($analyzed_function_collection) {
                $abstract_syntax_tree = $this->travelTree($ast, new NameResolver());
                $function_visitor     = new FunctionNodeVisitor($analyzed_function_collection, $file->getRealPath());
                $this->travelTree($abstract_syntax_tree, $function_visitor);
            }
        );
    }

    /**
     * Used to apply callbacks to source files and their AST's. Using the arguments of
     * the callback, files and their AST's could be analyzed using node visitors.
     *
     * @param string $target_project
     * @param callable $execute Callback with arguments Node[] (AST) and SplFileInfo
     */
    private function analyseSourceAsts(string $target_project, callable $execute)
    {
        $project_files = $this->retrieveProjectFiles($target_project);
        $ast_converter = new PhpAstConverter();

        foreach ($project_files as $file) {
            $abstract_syntax_tree = $ast_converter->convert($file->getContents());
            $execute($abstract_syntax_tree, $file);
        }
    }

    /**
     * Uses a node visitor to travel through an abstract syntax tree.
     *
     * @param Node[] $abstract_syntax_tree
     * @param NodeVisitorAbstract $node_visitor
     * @return Node[] AST after traversal
     */
    private function travelTree(array $abstract_syntax_tree, NodeVisitorAbstract $node_visitor): array
    {
        $ast_node_traveller = new NodeTraverser();
        $ast_node_traveller->addVisitor($node_visitor);
        return $ast_node_traveller->traverse($abstract_syntax_tree);
    }

    /**
     * Returns a Finder with the files matches to the target project its
     * source-files.
     *
     * @param string $target_project
     * @return Finder
     * @throws \InvalidArgumentException
     */
    private function retrieveProjectFiles(string $target_project): Finder
    {
        return (new Finder())
            ->files()
            ->in($target_project)
            ->exclude('vendor')
            ->name('*.php');
    }

    /**
     * Returns a Finder with the files matches to all the php files in the
     * directory. This is used during traversal to retrieve the docblocks
     * from other classes (including vendor classes).
     *
     * @param string $target_project
     * @return Finder
     * @throws \InvalidArgumentException
     */
    private function retrieveSourceFiles(string $target_project): Finder
    {
        return (new Finder())
            ->files()
            ->in($target_project)
            ->name('*.php');
    }
}
