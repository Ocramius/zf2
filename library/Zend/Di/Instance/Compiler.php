<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category  Zend
 * @package   Zend_Di
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Di\Instance;

use Zend\Di\Di,
    Zend\Di\Definition\Definition,
    Zend\Code\Generator\MethodGenerator,
    Zend\Code\Generator\ParameterGenerator,
    Zend\Code\Generator\ClassGenerator,
    Zend\Code\Generator\FileGenerator;

/**
 * Class for generating service locators from Di instances
 *
 * @category  Zend
 * @package   Zend_Di
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 */
class Compiler
{
    /**
     * @var string
     */
    protected $containerClass = 'ApplicationContext';

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var MethodGenerator
     */
    protected $methodGenerator;

    /**
     * @var ParameterGenerator
     */
    protected $parameterGenerator;

    /**
     * @var ClassGenerator
     */
    protected $classGenerator;

    /**
     * @var FileGenerator
     */
    protected $fileGenerator;

    /**
     * @var array
     */
    protected $instances = array();

    /**
     * @var array
     */
    protected $aliases;

    /**
     * Constructor
     *
     * @param Dumper $dumper
     * @param Di $di @deprecated
     */
    public function __construct(Dumper $dumper, Di $di)
    {
        // @todo should use the dumped data directly probably
        $this->instances = $dumper->getAllInjectedDefinitions();
        // @todo remove following as the code generator should not know about Di
        $this->aliases = $di->instanceManager()->getAliases();
        $this->methodGenerator = new MethodGenerator;
        $this->parameterGenerator = new ParameterGenerator;
        $this->classGenerator = new ClassGenerator;
        $this->fileGenerator = new FileGenerator;
    }

    /**
     * Set the class name for the generated service locator container
     *
     * @param  string $name
     * @return Compiler
     */
    public function setContainerClass($name)
    {
        $this->containerClass = $name;
        return $this;
    }

    /**
     * Get the class name for the generated service locator container
     *
     * @return string
     */
    public function getContainerClass()
    {
        return $this->containerClass;
    }

    /**
     * Set the namespace to use for the generated class file
     *
     * @param  string $namespace
     * @return Compiler
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Get the namespace to use for the generated class file
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Set filename to use for the generated class file
     *
     * @param string $filename
     * @return Compiler
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * Get filename to use for the generated class file
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Get class generator
     *
     * @return \Zend\Code\Generator\ClassGenerator
     */
    public function getClassGenerator()
    {
        return $this->classGenerator;
    }

    /**
     * Get file generator
     *
     * @return FileGenerator
     */
    public function getFileGenerator()
    {
        return $this->fileGenerator;
    }

    /**
     * Compiles a Di Definitions to a in a service locator, that extends Zend\Di\Di
     *
     * It uses Zend\Code\Generator\FileGenerator
     *
     * @param bool $write
     * @param string|null $filename
     * @return FileGenerator
     * @throws \InvalidArgumentException
     */
    public function compile($write = true)
    {

        $indent         = '    ';
        $caseStatements = array();
        $getters        = array();
        $instances      = $this->instances;

        foreach ($instances as $name => $data) {

            // Create instantiation code
            // @todo move to own method
            $getter = $this->normalizeAlias($name);
            $constructor = $data['instantiator']['name'];
            $constructor = $constructor ?: '__construct';
            $instantiatorParams = $this->buildParams($data['instantiator']['parameters']);

            if ('__construct' !== $constructor) {
                // Constructor callback
                if (is_callable($constructor)) {
                    $callback = $constructor;

                    if (is_array($callback)) {
                        $class = (is_object($callback[0])) ? get_class($callback[0]) : $callback[0];
                        $method = $callback[1];
                    } elseif (is_string($callback) && strpos($callback, '::') !== false) {
                        list($class, $method) = explode('::', $callback, 2);
                    }

                    $callback = var_export(array($class, $method), true);

                    if (count($instantiatorParams)) {
                        $creation = sprintf('$object = call_user_func(%s, %s);', $callback, implode(', ', $instantiatorParams));
                    } else {
                        $creation = sprintf('$object = call_user_func(%s);', $callback);
                    }
                } else if (is_string($constructor) && strpos($constructor, '->') !== false) {
                    list($class, $method) = explode('->', $constructor, 2);

                    if (!class_exists($class)) {
                        throw new \InvalidArgumentException('No class found: ' . $class);
                    }

                    $factoryGetter = $this->normalizeAlias($class);

                    if (count($instantiatorParams)) {
                        $creation = sprintf('$object = $this->' . $factoryGetter . '()->%s(%s);', $method, implode(', ', $instantiatorParams));
                    } else {
                        $creation = sprintf('$object = $this->' . $factoryGetter . '()->%s();', $method);
                    }
                } else {
                    throw new \InvalidArgumentException('Invalid instantiator supplied for class: ' . $name);
                }
            } else {
                $className = '\\' . trim($this->reduceAlias($name), '\\');

                if (count($instantiatorParams)) {
                    $creation = sprintf('$object = new %s(%s);', $className, implode(', ', $instantiatorParams));
                } else {
                    $creation = sprintf('$object = new %s();', $className);
                }
            }


            // Create method call code

            $className = ltrim($className, '\\');
            $creation .= "\n" . 'if ($isShared) {' . "\n" . $indent
                . '$this->instanceManager->addSharedInstance($object, \'' . $className . '\');' . "\n" . '}';
            $methods = '';

            foreach ($data['injections'] as $methodData) {
                $params = array();
                $methodName   = $methodData['name'];
                $methodParams = $methodData['parameters'];

                // Create method parameter representation
                $params = $this->buildParams($methodParams);

                // @todo this actually fixes injections with no params, but that should be handled at DIC/dumper level
                if(count($params)) {
                    $methods .= sprintf("\$object->%s(%s);\n", $methodName, implode(', ', $params));
                }
            }

            $storage = '';

            // Start creating getter
            $getterBody = '';

            // Creation and method calls
            $getterBody .= sprintf("%s\n", $creation);
            $getterBody .= $methods;

            // Stored service
            $getterBody .= $storage;

            // End getter body
            $getterBody .= "return \$object;\n";

            $getterDef = new MethodGenerator();
            $getterDef
                ->setName($getter)
                ->setParameter('isShared')
                ->setVisibility(MethodGenerator::VISIBILITY_PROTECTED)
                ->setBody($getterBody);
            $getters[] = $getterDef;

            // Build case statement and store
            $statement = '';
            $statement .= sprintf("%scase '%s':\n", $indent, $name);
            $statement .= sprintf("%sreturn \$this->%s(%s);\n", str_repeat($indent, 2), $getter, '$isShared');

            $caseStatements[] = $statement;
        }

        // Build switch statement
        $switch = sprintf(
            "if (%s) {\n%sreturn parent::newInstance(%s, %s, %s);\n}\n",
            '$params',
            $indent,
            '$name',
            '$params',
            '$isShared'
        );
        $switch .= sprintf(
            "switch (%s) {\n%s\n", '$name', implode("\n", $caseStatements)
        );
        $switch .= sprintf(
            "%sdefault:\n%sreturn parent::newInstance(%s, %s, %s);\n",
            $indent,
            str_repeat($indent, 2),
            '$name',
            '$params',
            '$isShared'
        );
        $switch .= "}\n\n";

        // Build newInstance() method
        $nameParam   = new ParameterGenerator();
        $nameParam->setName('name');

        $paramsParam = new ParameterGenerator();
        $paramsParam
            ->setName('params')
            ->setType('array')
            ->setDefaultValue(array());

        $isSharedParam = new ParameterGenerator();
        $isSharedParam
            ->setName('isShared')
            ->setDefaultValue(true);

        $get = new MethodGenerator();
        $get
            ->setName('newInstance')
            ->setParameters(array(
            $nameParam,
            $paramsParam,
            $isSharedParam,
        ))
            ->setBody($switch);

        // Create class code generation object
        $container = $this->getClassGenerator();
        $container
            ->setName($this->containerClass)
            ->setExtendedClass('Di')
            ->setMethod($get)
            ->setMethods($getters);

        // Create PHP file code generation object
        $classFile = $this->getFileGenerator();

        $classFile->setClass($container);
        $classFile->setUse('Zend\Di\Di');

        if (null !== $this->namespace) {
            $classFile->setNamespace($this->namespace);
        }

        $classFile->setFilename($this->getFilename());

        if ($write) {
            $classFile->write();
        }

        return $classFile;
    }

    /**
     * Reduce aliases
     *
     * @param  string $name
     * @return string
     */
    protected function reduceAlias($name)
    {
        if (isset($this->aliases[$name])) {
            return $this->reduceAlias($this->aliases[$name]);
        }
        return $name;
    }

    /**
     * Normalize an alias to a new instance method name
     *
     * @param  string $alias
     * @return string
     */
    protected function normalizeAlias($alias)
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]/', ' ', $alias);
        $normalized = 'new' . str_replace(' ', '', ucwords($normalized));
        return $normalized;
    }

    /**
     * Generates parameter strings to be used as injections, replacing reference parameters with their respective
     * getters
     *
     * @param array $params
     * @return array
     */
    protected function buildParams(array $params)
    {
        $normalizedParameters = array();

        foreach ($params as $parameter) {
            if (Dumper::REFERENCE === $parameter['type']) {
                $normalizedParameters[] = sprintf('$this->get(%s)', '\'' . $parameter['value'] . '\'');
            } else {
                $normalizedParameters[] = var_export($parameter['value'], true);
            }
        }

        return $normalizedParameters;
    }

}
