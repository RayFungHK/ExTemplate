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

		// Prepare template content as raw template frame
		$rootTemplateBlock = new TemplateBlock($this, '_ROOT', $tplContent);

		// Create a new queue in Root block
		$this->rootBlock = new TemplateQueue($rootTemplateBlock);

		// Setup the template pointer
		$this->pointer = $this->rootBlock;

		// Add current template to loaded template pool
		self::$LoadedTemplate[$this->tplName] = $this;
	}

	// Go to target block, syntax: (/?:Root)({a-z0-9_-}/*:blockName)([{a-z0-9_-/}+]:identifyName)?(/?:NextBlock)
	// Example: /blockA/blockB[identifyA]/blockC
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
					if ($blockPointer->hasBlock($path[2])) {
						// Set the block pointer
						$blockPointer->setBlock($path[2]);

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

	// Create new block into queue
	public function newBlock($identifyName = '') {
		$this->lastQueuePointer = $this->pointer->addQueue($identifyName);
		return $this;
	}

	// Create new block into queue after a block with specified idenetifyName
	public function newBlockAfter($targetIdentify, $identifyName = '') {
		$this->lastQueuePointer = $this->pointer->addQueue($identifyName, $targetIdentify, 1);
		return $this;
	}

	// Create new block into queue before a block with specified idenetifyName
	public function newBlockBefore($targetIdentify, $identifyName = '') {
		$this->lastQueuePointer = $this->pointer->addQueue($identifyName, $targetIdentify, 0);
		return $this;
	}

	// Enable it when you want to parse this template in PrintOut()
	public function setPostParse($enable) {
		$this->isPostParse = ($enable) ? true : false;
		return $this;
	}

	// Check the template is post-Parse or not
	public function isPostParse() {
		return $this->isPostParse;
	}

	// Parse all queue into parse pool or export to specified variable
	public function parse() {
		return $this->rootBlock->parse();
	}

	public function parseToFile() {

	}

	// Get the parsed content
	public function getParsedContent() {
		return $this->parsedContent;
	}

	// Assign to block current queue (Block Level)
	public function assign($variable, $value = '') {
		if (!$this->lastQueuePointer) {
			if ($this->pointer->isRoot()) {
				$this->lastQueuePointer = $this->pointer;
			} else {
				$this->lastQueuePointer = $this->pointer->getQueue();
			}
		}

		if (isset($this->lastQueuePointer)) {
			if (is_array($variable)) {
				foreach ($variable as $tagName => $value) {
					$this->assign($tagName, $value);
				}
			} else {
				$this->lastQueuePointer->assignTag($variable, $value);
			}
		}
		return $this;
	}

	// Assign to current file (File Level)
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

	// Get the super assign tag content
	public function getAssign($tagName) {
		return (array_key_exists($tagName, $this->assign)) ? $this->assign[$tagName] : null;
	}

	public function addMapping($path, $instance) {
		$path = $path . '/';
		$path = preg_replace('/\/+/', '/', $path);
		if (!isset($this->mapping[$path])) {
			$this->mapping[$path] = array();
		}
		$this->mapping[$path][$instance->getIdentifyName()] = &$instance;
		return $this;
	}
/*
	public function findBlock($path) {
		$path = preg_replace('/\/+/', '/', $path);
		$path = rtrim('/', $path);
		preg_match('/([^[]+)(\[[\w_+]+\])?(\/?)/i', $path, $matches, PREG_SET_ORDER);
		if ($matches) {
			foreach ($matches as $query) {
				if (isset($query[2])) {
					
				}
			}
		}
		if (isset($this->mapping[$path])) {
			
		}
	}
*/
	// Get Template object by name
	static public function GetTemplate($name) {
		if (isset(self::$LoadedTemplate[$name])) {
			return self::$LoadedTemplate[$name];
		}
		return null;
	}

	// Assign to global environment (Global Level)
	static public function GlobalAssign($variable, $value = '') {
		if (is_array($variable)) {
			foreach ($variable as $tagName => $value) {
				self::GlobalAssign($tagName, $value);
			}
		} else {
			self::$GlobalAssign[$variable] = $value;
		}
	}

	// Get the global assigned value
	static public function GetGlobalAssign($tagName) {
		return (array_key_exists($tagName, self::$GlobalAssign)) ? self::$GlobalAssign[$tagName] : null;
	}

	// Print out the parsed content from template pool on screen
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

	// Create a customized assign tag processor
	static public function CreateAssignProcessor($name, $callback) {
		if (is_string($name) && is_callable($callback)) {
			self::$AssignCallback[$name] = $callback;
		}
	}

	// Execute customized assign tag processor
	static public function ExecAssignProcessor($name, $argument) {
		if (isset(self::$AssignCallback[$name])) {
			return call_user_func_array(self::$AssignCallback[$name], $argument);
		}
		return '';
	}
}

class TemplateBlock {
	private $blockName = '';
	private $blockType = 'BLOCK';
	private $blockContent = array();
	private $blockTypeMapping = array();
	private $templateContainer = null;
	private $lastIndex = 0;
	private $parentBlock;
	private $isRoot = false;
	private $path = '';

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
			$this->path = $parentBlock->getPath() . $blockName . '/';
		}

		for ($index = $offset, $length = count($tplContent); $index < $length; $index++) {
			// Check if it is a block tag
			if (preg_match('/<!\-\- (START|END) ([a-z\_]+): (.+) \-\->/i', $tplContent[$index], $matches)) {
				if ($matches[1] == 'START') {
					// Create a template block under current block
					$tplObject = new TemplateBlock($this->templateContainer, $matches[3], $tplContent, $index + 1, $matches[2], $this);

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

	public function getPath() {
		return $this->path;
	}

	// Check the block is exists or not under current block
	public function hasBlock($blockName, $blockType = '') {
		if ($blockType) {
			return (isset($this->blockTypeMapping[$blockType][$blockName]));
		}
		return (isset($this->blockContent[$blockName]));
	}

	// Get the block
	public function getBlock($blockName) {
		if (isset($this->blockContent[$blockName])) {
			return $this->blockContent[$blockName];
		}
		return null;
	}

	// Get the current block type
	public function getBlockType() {
		return $this->blockType;
	}

	// Get stored block content
	public function getBlockContent() {
		return $this->blockContent;
	}

	// Get the block is root or not
	public function isRoot() {
		return $this->isRoot;
	}

	// Get the block Name
	public function getBlockName() {
		return $this->blockName;
	}

	// Get the template container
	public function getContainer() {
		return $this->templateContainer;
	}
}

class TemplateQueue {
	private $parent = null;
	private $identifyName = '';
	private $assign = array();
	private $queue = array();
	private $blockPointer = null;
	private $pointer = null;

	public function __construct($parent, $identifyName = '') {
		$this->parent = $parent;
		if (!$identifyName) {
			// Define a unique identify name if not specified
			$identifyName = '__TQ#' . sprintf('%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff));
		}
		$this->identifyName = $identifyName;
	}

	// Check is any block under current queue from parent template block
	public function hasBlock($blockName) {
		return $this->parent->hasBlock($blockName);
	}

	// Get identify name
	public function getIdentifyName() {
		return $this->identifyName;
	}

	// Add or Update AssignTag
	public function assignTag($variable, $value) {
		$this->assign[$variable] = $value;
		return $this;
	}

	// Get the block is root or not
	public function isRoot() {
		return $this->parent->isRoot();
	}

	// Set the block pointer
	public function setBlock($blockName) {
		if (!isset($this->queue[$blockName])) {
			$this->queue[$blockName] = array();
		}
		$this->blockPointer = $blockName;
		return $this;
	}

	// Get All Queues
	public function getAllQueues() {
		return (isset($this->queue[$this->blockPointer])) ? $this->queue[$this->blockPointer] : array();
	}

	// Get Current Queue
	public function getCurrentQueue() {
		return $this->pointer;
	}

	// Get Current Block Template
	public function getBlockTemplate() {
		return $this->parent->getBlock($this->blockPointer);
	}

	// Check is there any queue under current block pointer
	public function hasQueue() {
		return (isset($this->queue[$this->blockPointer]) && count($this->queue[$this->blockPointer]) > 0);
	}

	// Get the last queue or specified queue
	public function getQueue($identifyName = '') {
		if ($identifyName) {
			if (isset($this->queue[$this->blockPointer][$identifyName])) {
				return $this->queue[$this->blockPointer][$identifyName];
			}
		} else {
			return (count($this->queue[$this->blockPointer])) ? end($this->queue[$this->blockPointer]) : null;
		}
		return null;
	}

	// Add Queue to currrent queue
	public function addQueue($identifyName = '', $targetIdentify = '', $append = 1) {
		// Trim the identify name
		$identifyName = trim($identifyName);

		// If identify name not provided or not found in queue
		if (!$identifyName || !isset($this->queue[$this->blockPointer][$identifyName])) {
			// Create new template queue
			$tq = new TemplateQueue($this->parent->getBlock($this->blockPointer), $identifyName);
			// Set the queue to pointer
			$this->pointer = $tq;

			$keyIndex = FALSE;
			// If target identify name is provided, find the queue index
			if ($targetIdentify) {
				$keyIndex = array_search($targetIdentify, array_keys($this->queue[$this->blockPointer]));
			}

			// If no queue found or no target identify name is provided
			if ($keyIndex === FALSE) {
				// Add the queue to end of the queue list
				$this->queue[$this->blockPointer][$tq->getIdentifyName()] = $tq;
			} elseif (!$append && $keyIndex == 0) {
				// If it is prepend mode and the queue index was the first item, add the queue to begining of the queu list
				$this->queue = array($tq->getIdentifyName() => $tq) + $this->queue[$this->blockPointer];
			} else {
				// If it is a append mode, keyIndex + 1
				if ($append) {
					$keyIndex++;
				}

				// If keyIndex greater than current queue list length, just push into it
				if ($keyIndex > count($this->queue[$this->blockPointer]) - 1) {
					$this->queue[$this->blockPointer][$tq->getIdentifyName()] = $tq;
				} else {
					// Insert the queue to specified position
					array_splice($this->queue[$this->blockPointer], $keyIndex, 0, array($tq->getIdentifyName() => $tq));
				}
			}
		} else {
			// Set the pointer to target queue by identify name
			$this->pointer = $this->queue[$this->blockPointer][$identifyName];
		}

		return $this->pointer;
	}

	public function getTemplate() {
		return $this->parent;
	}

	public function parse() {
		// Get the parent template block content
		$templateContent = $this->parent->getBlockContent();

		// Initialize
		$readyParseBlock = array();
		$parsedContent = '';

		// ISEXISTS Block
		if ($this->parent->getBlockType() == 'IFEXISTS') {
			if (count($this->queue, COUNT_RECURSIVE) - count($this->queue) == 0) {
				return '';
			}
		}

		// IFNOTEXISTS Block
		if ($this->parent->getBlockType() == 'IFNOTEXISTS') {
			
		}

		if (count($templateContent)) {
			foreach ($templateContent as $identifyName => $content) {
				// If the line is a template block object
				if (is_a($content, 'TemplateBlock')) {
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
									} elseif (($result = $this->parent->getContainer()->getAssign($command[0]))) {
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
					} elseif (($result = $this->parent->getContainer()->getAssign($matches[1])) !== null) {
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
?>
