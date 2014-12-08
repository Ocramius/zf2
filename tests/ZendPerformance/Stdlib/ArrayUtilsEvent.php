<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendPerformance\Stdlib;

use Athletic\AthleticEvent;
use Zend\Stdlib\ArrayUtils;

/**
 * Performance tests for {@see \Zend\Stdlib\ArrayUtils}
 */
class ArrayUtilsEvent extends AthleticEvent
{
    /**
     * @var array
     */
    private $emptyArray = array();

    /**
     * @var array
     */
    private $numericKeysArray = array('foo', 'bar', 'baz');

    /**
     * @var array
     */
    private $stringKeysArray = array('foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz');

    /**
     * @var array
     */
    private $recursiveNumericKeysArray;

    /**
     * @var array
     */
    private $recursiveStringKeysArray;


    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $recursiveNumericKeysArray = $this->numericKeysArray;
        $recursiveStringKeysArray  = $this->stringKeysArray;

        for ($depth = 0; $depth < 10; $depth += 1) {
            $recursiveNumericKeysArray = array_merge(
                $this->numericKeysArray,
                array('recursion' => $recursiveNumericKeysArray)
            );
            $recursiveStringKeysArray = array_merge(
                $this->stringKeysArray,
                array('recursion' => $recursiveStringKeysArray)
            );
        }

        $this->recursiveNumericKeysArray = $recursiveNumericKeysArray;
        $this->recursiveStringKeysArray  = $recursiveStringKeysArray;
    }

    /**
     * @iterations 10000
     */
    public function mergeEmptyArrays()
    {
        ArrayUtils::merge($this->emptyArray, $this->emptyArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeNumericKeysIntoEmptyArray()
    {
        ArrayUtils::merge($this->emptyArray, $this->numericKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeStringKeysIntoEmptyArray()
    {
        ArrayUtils::merge($this->emptyArray, $this->stringKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeNumericKeysIntoStringKeysArray()
    {
        ArrayUtils::merge($this->stringKeysArray, $this->numericKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeStringKeysIntoNumericKeysArray()
    {
        ArrayUtils::merge($this->numericKeysArray, $this->stringKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeNumericKeysArrays()
    {
        ArrayUtils::merge($this->numericKeysArray, $this->numericKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeStringKeysArray()
    {
        ArrayUtils::merge($this->stringKeysArray, $this->stringKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeRecursiveNumericKeysIntoEmptyArray()
    {
        ArrayUtils::merge($this->emptyArray, $this->recursiveNumericKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeRecursiveNumericKeysArray()
    {
        ArrayUtils::merge($this->recursiveNumericKeysArray, $this->recursiveNumericKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeRecursiveNumericKeysIntoRecursiveStringKeysArray()
    {
        ArrayUtils::merge($this->recursiveStringKeysArray, $this->recursiveNumericKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeRecursiveStringKeysIntoEmptyArray()
    {
        ArrayUtils::merge($this->emptyArray, $this->recursiveStringKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeRecursiveStringKeysArray()
    {
        ArrayUtils::merge($this->recursiveStringKeysArray, $this->recursiveStringKeysArray);
    }

    /**
     * @iterations 10000
     */
    public function mergeRecursiveStringKeysIntoRecursiveNumericKeysArray()
    {
        ArrayUtils::merge($this->recursiveNumericKeysArray, $this->recursiveStringKeysArray);
    }
}
