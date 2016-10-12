<?php
class Template {
	static public $Assign = array();
	static public $LoadedTemplate = array();
	static private $GlobalAssign = array();
	static private $TemplatePool = array();
	static private $AssignCallback = array();

	private $assign = array();
	private $tplName = '';
	private $pointer = null;
	private $lastQueuePointer = null;
	private $parsed = false;
	private $rootBlock = null;
	private $isPostParse = false;
	private $mapping = array();

	/**
	 * constructor, convert template file into TemplateStructure
	 * 
	 * @access public
	 * @param string $tplPath
	 * @param string $tplName (default: '')
	 * @return void
	 */
	public function __construct($tplPath, $tplName = '') {
		$tplContent = file($tplPath);
		if ($tplName) {
			$this->tplName = $tplName;
		} else {
			if (($pos = strrpos('/', $tplPath)) !== FALSE) {
				$this->tplName = substr($tplPath, $pos + 1);
			} else {
				$this->tplName = $tplPath;
			}
		}

		// Convert HTML to TemplateStructure
		$rootTemplateBlock = new TemplateStructure($this, '_ROOT', $tplContent);

		// Create a new queue in Root block
		$this->rootBlock = new TemplateQueue($rootTemplateBlock);

		// Setup the template pointer
		$this->pointer = $this->rootBlock;

		// Add current template to loaded template pool
		self::$LoadedTemplate[$this->tplName] = $this;
	}

	/**
	 * Go to target block, syntax: (/?:Root)({a-z0-9_-}/*:blockName)([{a-z0-9_-/}+]:identifyName)?(/?:NextBlock)
	 * Example: /blockA/blockB[identifyA]/blockC
	 * 
	 * @access public
	 * @param string $namespace
	 * @return Template
	 */
	public function gotoBlock($namespace) {
		// Check the namespace is string or callback
		if (is_string($namespace)) {
			// Remove duplicated slashes
			$namespace = preg_replace('/\/+/', '/', $namespace);

			// Remove the last slash
			if (strlen($namespace) > 2) {
				$namespace = rtrim($namespace, '/');
			}

			// If the path start from '/', reset to Root block
			if ($namespace[0] == '/') {
				$namespace = substr($namespace, 1);
				$blockPointer = $this->rootBlock;
			} else {
				$blockPointer = $this->pointer->getCurrentQueue();
			}

			// Extract the path query in block
			preg_match_all('/(([a-z0-9_\-]+)(\[([a-z0-9_\-\/]+)\])?)(\/?)/i', $namespace, $matches, PREG_SET_ORDER);

			$this->lastQueuePointer = null;
			if ($matches) {
				foreach ($matches as $path) {
					// Check the block is exists under current query
					if ($blockPointer->getStructure()->hasBlock($path[2])) {
						// Set the block pointer
						$blockPointer->setPointer($path[2]);
						
						if ($path[4]) {
							// Set the pointer as target queue by specified identify name
							$blockPointer = $blockPointer->getQueue($path[4]);
							$this->lastQueuePointer = $blockPointer;
						} else {
							// If there is any path find remaining, get the queue
							if ($path[5]) {
								// Check is any queue under the block
								if ($blockPointer->hasQueue()) {
									// Set the pointer as latest queue
									$blockPointer = $blockPointer->getQueue();
									$this->lastQueuePointer = $blockPointer;
								} else {
									throw new Exception('Cannot find any queue for next path');
								}
							}
						}
					} else {
						throw new Exception('Block ' . $namespace . ' not found.');
					}
				}
			}

			// Update the pointer
			$this->pointer = $blockPointer;
		}
		return $this;
	}


	/**
	 * Back to parent level queue, or you can provide specified block name 
	 * with identify name until the pointer reach to root
	 *
	 * @access public
	 * @param string $namespace (default: '')
	 * @return Template
	 */
	public function parent($namespace = '') {
		$namespace = trim($namespace);
		if ($namespace) {
			$identifyName = '';
			if (preg_match('/(.*)(\[((?>.|(?R))*)\])/', $namespace, $matches)) {
				$blockName = $matches[1];
				$identifyName = $matches[3];
			} else {
				$blockName = $namespace;
			}

			do {
				if ($this->pointer->getStructure()->getBlockName() == $blockName) {
					// Go to parent queue and set the pointer
					$this->pointer = $this->pointer->getParent();
					$this->pointer->setPointer($blockName);
					$this->lastQueuePointer = ($identifyName) ? $this->pointer->getQueue($identifyName) : $this->pointer->getCurrentQueue();
					return $this;
				}
			} while ($this->pointer = $this->pointer->getParent());

			// If the pointer reach the root, reset
			$this->pointer = $this->rootBlock;
			$this->lastQueuePointer = null;
		} else {
			$this->pointer = $this->pointer->getParent();
			if (!$this->pointer) {
				$this->pointer = $this->rootBlock;
				$this->lastQueuePointer = null;
			} else {
				$this->lastQueuePointer = $this->pointer->getCurrentQueue();
			}
		}
		return $this;
	}

	/**
	 * Create new block into queue
	 * 
	 * @access public
	 * @param string $identifyName (default: '')
	 * @return Template
	 */
	public function newBlock($identifyName = '') {
		$this->lastQueuePointer = $this->pointer->addQueue($identifyName);
		return $this;
	}

	/**
	 * Create new block into queue after a block with specified idenetifyName
	 * 
	 * @access public
	 * @param string $targetIdentify
	 * @param string $identifyName (default: '')
	 * @return Template
	 */
	public function newBlockAfter($targetIdentify, $identifyName = '') {
		$this->lastQueuePointer = $this->pointer->addQueue($identifyName, $targetIdentify, 1);
		return $this;
	}

	/**
	 * Create new block into queue before a block with specified idenetifyName
	 * 
	 * @access public
	 * @param string $targetIdentify
	 * @param string $identifyName (default: '')
	 * @return Template
	 */
	public function newBlockBefore($targetIdentify, $identifyName = '') {
		$this->lastQueuePointer = $this->pointer->addQueue($identifyName, $targetIdentify, 0);
		return $this;
	}

	/**
	 * Set this template to PrintOut queue.
	 * 
	 * @access public
	 * @param mixed $enable
	 * @return Template
	 */
	public function setPostParse($enable) {
		$this->isPostParse = ($enable) ? true : false;
		return $this;
	}

	/**
	 * Check the template is post-Parse or not
	 * 
	 * @access public
	 * @return bool
	 */
	public function isPostParse() {
		return $this->isPostParse;
	}

	/**
	 * Parse all queue into parse pool or export to specified variable
	 * 
	 * @access public
	 * @return string
	 */
	public function parse() {
		return $this->rootBlock->parse();
	}

	/**
	 * Get the parsed content
	 * 
	 * @access public
	 * @return bool
	 */
	public function getParsedContent() {
		return $this->parsedContent;
	}

	/**
	 * Assign to block current queue (Block Level)
	 * If variable is a callback function, pass the current assign tag and
	 * reassigned the tag value
	 * 
	 * @access public
	 * @param mixed $variable
	 * @param string $value (default: '')
	 * @return Template
	 */
	public function assign($variable, $value = '') {
		if (!$this->lastQueuePointer) {
			if ($this->pointer->getStructure()->isRoot()) {
				$this->lastQueuePointer = $this->pointer;
			} else {
				$this->lastQueuePointer = $this->pointer->getQueue();
			}
		}

		if (isset($this->lastQueuePointer)) {
			if (!is_string($variable) && is_callable($variable)) {
				echo $variable;
				$newAssigned = $variable($this->lastQueuePointer->getAssigned());
				if (is_array($newAssigned) && count($newAssigned)) {
					return $this->assign($newAssigned);
				}
			} else {
				if (is_array($variable)) {
					foreach ($variable as $tagName => $value) {
						$this->assign($tagName, $value);
					}
				} else {
					$this->lastQueuePointer->assignTag($variable, $value);
				}
			}
		}
		return $this;
	}

	/**
	 * Map the queue to mapping list
	 * 
	 * @access public
	 * @param string $path
	 * @param TemplateQueue $instance
	 * @return void
	 */
	public function mapPath($path, $instance) {
		if (is_a($instance, 'TemplateQueue')) {
			if (!isset($this->mapping[$path])) {
				$this->mapping[$path] = array();
			}
			$this->mapping[$path][$instance->getIdentifyName()] = $instance;
		}
		return $this;
	}

	/**
	 * Mount the target queue to pointer
	 * 
	 * @access public
	 * @param TemplateQueue $queue
	 * @return Template
	 */
	public function mountQueue($queue) {
		if (is_a($queue, 'TemplateQueue')) {
			$this->pointer = $queue;
			$this->lastQueuePointer = $this->pointer;
		}
		return $this;
	}

	/**
	 * Find the target queues by namespace syntax
	 * 
	 * @access public
	 * @param string $namespace
	 * @return TemplateQueuePack
	 */
	public function findBlock($namespace) {
		$namespace = trim($namespace);
		if ($namespace) {
			// If namespece is not start from root, merge the parent block path
			if ($namespace[0] != '/') {
				if (!$this->pointer->getStructure()->isRoot()) {
					$namespace = $this->pointer->getPath() . '/' . $namespace;
				}
			}

			// Explode the namespace by '/'
			$delimiter = explode('/', $namespace);

			// Get the target queue that we wanted
			$targetQueue = array_pop($delimiter);
			$identifyName = '';

			// If the target is including identify name, split it
			if (preg_match('/(.*)(\[((?>.|(?R))*)\])/', $targetQueue, $matches)) {
				$blockName = $matches[1];
				$identifyName = $matches[3];
			} else {
				$blockName = $targetQueue;
			}

			$foundQueue = array();
			// Generate the full path of target queue
			$orgBasicPath = implode('/', $delimiter) . '/' . $blockName;
			// Remove any identify name
			$basicPath = preg_replace('/(\[((?>.|(?R))*)\])/U', '', $orgBasicPath);

			// Search queue mapping and get the list of queue
			$foundQueue = array();
			if (isset($this->mapping[$basicPath])) {
				if ($identifyName) {
					// Get the queue by specified identify name
					if (isset($this->mapping[$basicPath][$identifyName])) {
						$foundQueue = array($identifyName => $this->mapping[$basicPath][$identifyName]);
					}
				} else {
					$foundQueue = $this->mapping[$basicPath];
				}
			}

			// Because we have removed all identify name from basic path, if it has not matched as orginal basic path,
			// we need deep scanning to filter the queue
			if ($orgBasicPath != $basicPath) {
				// Remove the empty clip
				array_shift($delimiter);
				$searchPath = '/';

				// Scanning every path
				while (($clip = array_shift($delimiter)) != null) {
					// If the clip is including identify name, start filtering
					if (preg_match('/(.*)(\[((?>.|(?R))*)\])/', $clip, $matches)) {
						$index = 0;
						$searchPath .= ($searchPath == '/') ? $matches[1] : '/' . $matches[1];
						foreach ($foundQueue as $idenetifyName => $queue) {
							// If the queue is not the child of parent block with specified idenitify name, remove it
							if (!$queue->isChildOf($searchPath, $matches[1], $matches[3])) {
								unset($foundQueue[$idenetifyName]);
							}
						}
					} else {
						$searchPath .= ($searchPath == '/') ? $clip : '/' . $clip;
					}
				}
			}
			return new TemplateQueuePack($this, $foundQueue);
		}
		return new TemplateQueuePack($this);
	}

	/**
	 * Assign tag to current file (File Level)
	 * 
	 * @access public
	 * @param mixed $variable
	 * @param string $value (default: '')
	 * @return Template
	 */
	public function superAssign($variable, $value = '') {
		if (is_array($variable)) {
			foreach ($variable as $tagName => $value) {
				$this->superAssign($tagName, $value);
			}
		} else {
			$this->assign[$variable] = $value;
		}
		return $this;
	}

	/**
	 * Return file Level assigned value
	 * 
	 * @access public
	 * @param string $tagName
	 * @return mixed
	 */
	public function getAssign($tagName) {
		return (array_key_exists($tagName, $this->assign)) ? $this->assign[$tagName] : null;
	}

	/**
	 * Return Template object by name
	 * 
	 * @access public
	 * @static
	 * @param string $name
	 * @return Template
	 */
	static public function GetTemplate($name) {
		if (isset(self::$LoadedTemplate[$name])) {
			return self::$LoadedTemplate[$name];
		}
		return null;
	}

	/**
	 * Assign tag to global environment (Global Level)
	 * 
	 * @access public
	 * @static
	 * @param mixed $variable
	 * @param string $value (default: '')
	 * @return void
	 */
	static public function GlobalAssign($variable, $value = '') {
		if (is_array($variable)) {
			foreach ($variable as $tagName => $value) {
				self::GlobalAssign($tagName, $value);
			}
		} else {
			self::$GlobalAssign[$variable] = $value;
		}
	}

	/**
	 * Return Global Level assigned value.
	 * 
	 * @access public
	 * @static
	 * @param string $tagName
	 * @return mixed
	 */
	static public function GetGlobalAssign($tagName) {
		return (array_key_exists($tagName, self::$GlobalAssign)) ? self::$GlobalAssign[$tagName] : null;
	}

	/**
	 * Print out the parsed content from template pool on screen
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
	static public function PrintOut() {
		if (count(self::$LoadedTemplate)) {
			foreach (self::$LoadedTemplate as $loadedTemplate) {
				if ($loadedTemplate->isPostParse()) {
					$loadedTemplate->setPostParse(false);
					echo $loadedTemplate->parse();
				}
			}
		}
	}

	/**
	 * Bind and Create a customized assign tag processor
	 * 
	 * @access public
	 * @static
	 * @param string $name
	 * @param callable $callback
	 * @return void
	 */
	static public function CreateAssignProcessor($name, $callback) {
		if (is_string($name) && is_callable($callback)) {
			self::$AssignCallback[$name] = $callback;
		}
	}

	/**
	 * Execute the customized assign tag processor
	 * 
	 * @access public
	 * @static
	 * @param string $name
	 * @param mixed $argument
	 * @return mixed
	 */
	static public function ExecAssignProcessor($name, $argument) {
		if (isset(self::$AssignCallback[$name])) {
			return call_user_func_array(self::$AssignCallback[$name], $argument);
		}
		return '';
	}
}

class TemplateStructure {
	private $blockName = '';
	private $blockType = 'BLOCK';
	private $blockContent = array();
	private $blockTypeMapping = array();
	private $templateContainer = null;
	private $lastIndex = 0;
	private $parentBlock;
	private $isRoot = false;
	private $path = '';

	/**
	 * constructor
	 * 
	 * @access public
	 * @param Template $templateContainer
	 * @param string $blockName
	 * @param array $tplContent
	 * @param int $offset (default: 0)
	 * @param string $blockType (default: 'BLOCK')
	 * @param TemplateStructure $parentBlock (default: null)
	 * @return void
	 */
	public function __construct($templateContainer, $blockName, $tplContent, $offset = 0, $blockType = 'BLOCK', $parentBlock = null) {
		// Setup the block name, type and parent
		$this->blockName = $blockName;
		$this->blockType = $blockType;
		$this->parentBlock = $parentBlock;
		$this->templateContainer = $templateContainer;

		if (is_null($parentBlock)) {
			$this->isRoot = true;
			$this->path = '/';
		} else {
			$this->path = ($parentBlock->getPath() == '/') ? '' : $parentBlock->getPath();
			$this->path .= '/' . $blockName;
		}

		for ($index = $offset, $length = count($tplContent); $index < $length; $index++) {
			// Check if it is a block tag
			if (preg_match('/<!\-\- (START|END) ([a-z\_]+): (.+) \-\->/i', $tplContent[$index], $matches)) {
				if ($matches[1] == 'START') {
					// Create a template block under current block
					$tplObject = new TemplateStructure($this->templateContainer, $matches[3], $tplContent, $index + 1, $matches[2], $this);

					$this->blockContent[$matches[3]] = $tplObject;
					if (!isset($this->blockTypeMapping[$matches[2]])) {
						$this->blockTypeMapping[$matches[2]] = array();
					}
					$this->blockTypeMapping[$matches[2]][]  = $tplObject;
					// Skip the processed line
					$index = $tplObject->lastIndex;
				} elseif ($matches[1] == 'END') {
					if ($matches[3] == $blockName) {
						// Set last processed line
						$this->lastIndex = $index;
						break;
					} else {
						throw new Exception('Invalid End Tag <' . $matches[3] . '> in current block section <' . $blockName . '>.');
					}
				}
			} else {
				// Put current line into content pool
				$this->blockContent[] = $tplContent[$index];
			}
		}

		$this->lastIndex = $index;
		// If the parentBlock is null, set it as Root
	}

	/**
	 * Return Structure Path
	 * 
	 * @access public
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Check the block is exists under template structure
	 * 
	 * @access public
	 * @param string $blockName
	 * @param string $blockType (default: '')
	 * @return bool
	 */
	public function hasBlock($blockName, $blockType = '') {
		if ($blockType) {
			return (isset($this->blockTypeMapping[$blockType][$blockName]));
		}
		return (isset($this->blockContent[$blockName]));
	}

	/**
	 * Check the block type is exists under template structure
	 * 
	 * @access public
	 * @param string $blockType
	 * @return bool
	 */
	public function hasBlockType($blockType) {
		return (isset($this->blockTypeMapping[$blockType]));
	}

	/**
	 * Reture the structure by specified name
	 * 
	 * @access public
	 * @param string $blockName
	 * @return TemplateStructure
	 */
	public function getBlock($blockName) {
		if (isset($this->blockContent[$blockName])) {
			return $this->blockContent[$blockName];
		}
		return null;
	}

	/**
	 * Reture the block type
	 * 
	 * @access public
	 * @return string
	 */
	public function getBlockType() {
		return $this->blockType;
	}

	/**
	 * Return stored block structure content
	 * 
	 * @access public
	 * @return string
	 */
	public function getBlockContent() {
		return $this->blockContent;
	}

	/**
	 * Return the block is root or not
	 * 
	 * @access public
	 * @return bool
	 */
	public function isRoot() {
		return $this->isRoot;
	}

	/**
	 * Return the block name
	 * 
	 * @access public
	 * @return string
	 */
	public function getBlockName() {
		return $this->blockName;
	}

	/**
	 * Return the Template
	 * 
	 * @access public
	 * @return void
	 */
	public function getContainer() {
		return $this->templateContainer;
	}
}

class TemplateQueue {
	private $structure = null;
	private $identifyName = '';
	private $assign = array();
	private $queue = array();
	private $blockPointer = null;
	private $pointerBlockName = null;
	private $queuePointer = null;
	private $parentQueue = null;

	/**
	 * constructor
	 * 
	 * @access public
	 * @param Template $structure
	 * @param string $identifyName (default: '')
	 * @param TemplateQueue $parentQueue (default: null)
	 * @return void
	 */
	public function __construct($structure, $identifyName = '', $parentQueue = null) {
		$this->structure = $structure;
		if (!$identifyName) {
			// Define a unique identify name if not specified
			$identifyName = '__TQ#' . sprintf('%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff));
		} else {
		}
		$this->identifyName = $identifyName;
		$this->queuePointer = $this;
		$this->structure->getContainer()->mapPath($structure->getPath(), $this);
		if ($parentQueue) {
			$this->parentQueue = $parentQueue;
		}
	}

	/**
	 * Return the queue identify name.
	 * 
	 * @access public
	 * @return string
	 */
	public function getIdentifyName() {
		return $this->identifyName;
	}

	/**
	 * Return the assigned tag in this queue
	 * 
	 * @access public
	 * @return void
	 */
	public function getAssigned() {
		return $this->assign;
	}

	/**
	 * Return the binded structure
	 * 
	 * @access public
	 * @return TemplateStructure
	 */
	public function getStructure() {
		return $this->structure;
	}

	/**
	 * Bind assign tag and value
	 * 
	 * @access public
	 * @param mixed $variable
	 * @param mixed $value
	 * @return TemplateQueue
	 */
	public function assignTag($variable, $value) {
		$this->assign[$variable] = $value;
		return $this;
	}

	/**
	 * Set the block pointer
	 * 
	 * @access public
	 * @param string $blockName
	 * @return TemplateQueue
	 */
	public function setPointer($blockName) {
		if (!isset($this->queue[$blockName])) {
			$this->queue[$blockName] = array();
		}
		$this->blockPointer = &$this->queue[$blockName];
		$this->pointerBlockName = $blockName;
		return $this;
	}

	/**
	 * Return all queues that the block pointer marked
	 * 
	 * @access public
	 * @return array
	 */
	public function getAllQueues() {
		return (isset($this->blockPointer)) ? $this->blockPointer : array();
	}

	/**
	 * Return the queue that the queue pointer marked
	 * 
	 * @access public
	 * @return TemplateQueue
	 */
	public function getCurrentQueue() {
		return $this->queuePointer;
	}

	/**
	 * Return true if there is any queue exists in current block pointer
	 * 
	 * @access public
	 * @return bool
	 */
	public function hasQueue() {
		return (isset($this->blockPointer) && count($this->blockPointer) > 0);
	}

	/**
	 * Return the last queue or the queue with specified identify name
	 * 
	 * @access public
	 * @param string $identifyName (default: '')
	 * @return TemplateQueue
	 */
	public function getQueue($identifyName = '') {
		if ($identifyName) {
			if (isset($this->blockPointer[$identifyName])) {
				return $this->blockPointer[$identifyName];
			}
		} else {
			return (count($this->blockPointer)) ? end($this->blockPointer) : null;
		}
		return null;
	}

	/**
	 * Return the parent queue
	 * 
	 * @access public
	 * @return TemplateQueue
	 */
	public function getParent() {
		return $this->parentQueue;
	}


	/**
	 * Check current queue is child of specified path with identify name
	 * 
	 * @access public
	 * @param string $path
	 * @param string $blockName
	 * @param string $identifyName
	 * @return bool
	 */
	public function isChildOf($path, $blockName, $identifyName) {
		if ($path == $this->getStructure()->getPath() && $blockName == $this->getStructure()->getBlockName()) {
			return $identifyName == $this->getIdentifyName();
		} elseif ($this->parentQueue) {
			return $this->parentQueue->isChildOf($path, $blockName, $identifyName);
		} else {
			return false;
		}
	}

	/**
	 * Remove current queue from list, and no longer getting parsed
	 * 
	 * @access public
	 * @param TemplateQueue $queue
	 * @return TemplateQueue
	 */
	public function detach($queue) {
		if (is_a($queue, 'TemplateQueue')) {
			$idenetifyName = $queue->getIdentifyName();
			$blockName = $queue->getStructure()->getBlockName();
			if (isset($this->queue[$blockName][$idenetifyName])) {
				unset($this->queue[$blockName][$idenetifyName]);
			}
		}
		return $this;
	}

	/**
	 * Create a new queue
	 * 
	 * @access public
	 * @param string $identifyName (default: '')
	 * @param string $targetIdentify (default: '')
	 * @param int $append (default: 1)
	 * @return TemplateQueue
	 */
	public function addQueue($identifyName = '', $targetIdentify = '', $append = 1) {
		// Trim the identify name
		$identifyName = trim($identifyName);

		// If identify name not provided or not found in queue
		if (!$identifyName || !isset($this->blockPointer[$identifyName])) {
			// Create new template queue
			$tq = new TemplateQueue($this->structure->getBlock($this->pointerBlockName), $identifyName, $this);

			// Set the queue to pointer
			$this->queuePointer = $tq;

			$keyIndex = FALSE;
			// If target identify name is provided, find the queue index
			if ($targetIdentify) {
				$keyIndex = array_search($targetIdentify, array_keys($this->blockPointer));
			}

			// If no queue found or no target identify name is provided
			if ($keyIndex === FALSE) {
				// Add the queue to end of the queue list
				$this->blockPointer[$tq->getIdentifyName()] = $tq;
			} elseif (!$append && $keyIndex == 0) {
				// If it is prepend mode and the queue index was the first item, add the queue to begining of the queue list
				$this->blockPointer = array($tq->getIdentifyName() => $tq) + $this->blockPointer;
			} else {
				// If it is a append mode, keyIndex + 1
				if ($append) {
					$keyIndex++;
				}

				// If keyIndex greater than current queue list length, just push into it
				if ($keyIndex > count($this->blockPointer) - 1) {
					$this->blockPointer[$tq->getIdentifyName()] = $tq;
				} else {
					// Insert the queue to specified position
					array_splice($this->blockPointer, $keyIndex, 0, array($tq->getIdentifyName() => $tq));
				}
			}
		} else {
			// Set the pointer to target queue by identify name
			$this->queuePointer = $this->blockPointer[$identifyName];
		}

		return $this->queuePointer;
	}

	/**
	 * Parse all queue as content
	 * 
	 * @access public
	 * @return string
	 */
	public function parse() {
		// Get the parent template block content
		$templateContent = $this->structure->getBlockContent();

		// Initialize
		$readyParseBlock = array();
		$parsedContent = '';

		// IFEXISTS Block
		if ($this->structure->getBlockType() == 'IFEXISTS') {
			if (count($this->queue, COUNT_RECURSIVE) - count($this->queue) == 0) {
				// If no IFNOTEXISTS block under the structure, ignore to display
				if (!$this->structure->hasBlockType('IFNOTEXISTS')) {
					return '';
				}
			}
		}

		if (count($templateContent)) {
			foreach ($templateContent as $identifyName => $content) {
				// If the line is a template block object
				if (is_a($content, 'TemplateStructure')) {
					// IFNOTEXISTS Block
					// If there is no other block queued in same level, parse content
					if ($content->getBlockType() == 'IFNOTEXISTS' && count($this->queue, COUNT_RECURSIVE) - count($this->queue) == 0) {
						$this->setPointer($content->getBlockName())->addQueue();
					}

					// Check is there any queue was created
					if (isset($this->queue[$content->getBlockName()]) && count($this->queue[$content->getBlockName()])) {
						// Parse each template queue
						foreach ($this->queue[$content->getBlockName()] as $blockQueue) {
							// Assign an unique name for block
							$identifyName = '{__BLOCK#' . sprintf('%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff)) . '}';
							$parsedContent .= $identifyName;

							// Put the parsed content to post-parse list
							$readyParseBlock[$identifyName] = $blockQueue->parse();
						}
					}
				} else {
					$parsedContent .= $content;
				}
			}

			// Caller Process
			$parsedContent = preg_replace_callback(
				'/{{((?>.|(?R))*)}}/u',
				function($matches) {
					if ($matches[1]) {
						$tag = explode('::', $matches[1]);
						if (isset($tag[0])) {
							$command = explode(' ', $tag[0]);
							$caller = array_shift($command);
							$argument = array(
								$command,
								(isset($tag[1])) ? $tag[1] : ''
							);

							// Default Processor 'Switch': If value is true, return the content
							if ($caller == 'SWITCH') {
								if (isset($command[0])) {
									if (array_key_exists($command[0], $this->assign) && $this->assign[$command[0]]) {
										// Queue level assign
										return (isset($tag[1])) ? $tag[1] : '';
									} elseif (($result = $this->structure->getContainer()->getAssign($command[0]))) {
										// Template Container level assign
										return (isset($tag[1])) ? $tag[1] : '';
									} elseif (($result = Template::GetGlobalAssign($command[0]))) {
										// Global level assign
										return (isset($tag[1])) ? $tag[1] : '';
									}
								}
							} else {
								return Template::ExecAssignProcessor($caller, $argument);
							}
						}
					}
					return '';
				},
				$parsedContent
			);
			// Search and Replace the assigne tag
			$parsedContent = preg_replace_callback(
				'/{([\w_]+)}/u',
				function($matches) {
					if (array_key_exists($matches[1], $this->assign)) {
						// Queue level assign
						return $this->assign[$matches[1]];
					} elseif (($result = $this->structure->getContainer()->getAssign($matches[1])) !== null) {
						// Template Container level assign
						return $result;
					} elseif (($result = Template::GetGlobalAssign($matches[1])) !== null) {
						// Global level assign
						return $result;
					}
					return $matches[0];
				},
				$parsedContent
			);
			// Put back the parsed content to content pool
			$parsedContent = str_replace(array_keys($readyParseBlock), array_values($readyParseBlock), $parsedContent);
		}
		return $parsedContent;
	}
}

class TemplateQueuePack {
	private $queues = array();
	private $templateContainer = null;
	private $indexQueues = null;

	/**
	 * constructor
	 * 
	 * @access public
	 * @param Template $templateContainer
	 * @param mixed $queues (default: array())
	 * @return void
	 */
	public function __construct($templateContainer, $queues = array()) {
		$this->queues = (is_array($queues)) ? $queues : array($queues);
		$this->templateContainer = $templateContainer;
	}

	/**
	 * Assign tag to all queue
	 * 
	 * @access public
	 * @param mixed $variable
	 * @param mixed $value (default: '')
	 * @return void
	 */
	public function assign($variable, $value = '') {
		if (!is_string($variable) && is_callable($variable)) {
			foreach ($this->queues as $queue) {
				$newAssigned = $variable($queue->getAssigned());
				if (is_array($newAssigned) && count($newAssigned)) {
					foreach ($newAssigned as $tagName => $value) {
						$queue->assignTag($tagName, $value);
					}
				}
			}
		} else {
			if (is_array($variable)) {
				foreach ($variable as $tagName => $value) {
					$this->assign($tagName, $value);
				}
			} else {
				foreach ($this->queues as $queue) {
					$queue->assignTag($variable, $value);
				}
			}
		}
		return $this;
	}

	/**
	 * Mount the specified queue by identify name
	 * 
	 * @access public
	 * @param string $idenetifyName
	 * @return TemplateQueuePack
	 */
	public function mountQueueByIdentify($idenetifyName) {
		if (isset($this->queues[$idenetifyName])) {
			$this->templateContainer->mountQueue($this->queues[$idenetifyName]);
		}
		return $this;
	}

	/**
	 * Mount the specified queue by index
	 * 
	 * @access public
	 * @param int $index
	 * @return TemplateQueuePack
	 */
	public function mountQueueByIndex($index) {
		$index = intval($index);
		if ($this->indexQueues == null) {
			$this->indexQueues = array_values($this->queues);
		}
		if (isset($this->indexQueues[$index])) {
			$this->templateContainer->mountQueue($this->indexQueues[$index]);
		}
		return $this;
	}

	/**
	 * Mount the first queue as current TemplateQueue
	 * 
	 * @access public
	 * @return TemplateQueuePack
	 */
	public function mountQueue() {
		if (count($this->queues)) {
			$this->templateContainer->mountQueue(reset($this->queues));
		}
		return $this;
	}

	/**
	 * Detach all queue
	 * 
	 * @access public
	 * @return void
	 */
	public function detach() {
		foreach ($this->queues as $identifyName => $queue) {
			if (!$queue->getStructure()->isRoot()) {
				$queue->getParent()->detach($queue);
				unset($this->queues[$identifyName]);
			}
		}
		return $this;
	}

	/**
	 * Get the specified queue by idenetify name
	 * 
	 * @access public
	 * @param string $idenetifyName
	 * @return TemplateQueuePack
	 */
	public function getQueueByIdentify($idenetifyName) {
		if (isset($this->queues[$idenetifyName])) {
			return new TemplateQueuePack($this->templateContainer, $this->queues[$idenetifyName]);
		}
		return new TemplateQueuePack($this->templateContainer);
	}

	/**
	 * Get the specified queue by index
	 * 
	 * @access public
	 * @param int $index
	 * @return TemplateQueuePack
	 */
	public function getQueueByIndex($index) {
		$index = intval($index);
		if ($this->indexQueues == null) {
			$this->indexQueues = array_values($this->queues);
		}
		if (isset($this->indexQueues[$index])) {
			return new TemplateQueuePack($this->templateContainer, $this->indexQueues[$index]);
		}
		return new TemplateQueuePack($this->templateContainer);
	}
}
?>
