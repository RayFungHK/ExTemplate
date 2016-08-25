<?php
class Template {
	static public $Assign = array();
	static public $LoadedTemplate = array();
	static private $GlobalAssign = '';
	static private $TemplatePool = array();
	static private $AssignCallback = array();

	private $tplName = '';
	private $pointer = null;
	private $lastQueuePointer = null;
	private $parsedContent = '';
	private $parsed = false;
	private $rootBlock = null;

	public function __construct($tplPath, $tplName = '') {
		$tplContent = file($tplPath);
		$this->tplName = $tplName;

		// Prepare template content as raw template frame
		$rootTemplateBlock = new TemplateBlock($this, '_ROOT', $tplContent);

		// Create a new queue in Root block
		$this->rootBlock = new TemplateQueue($rootTemplateBlock);

		// Setup the template pointer
		$this->pointer = $this->rootBlock;

		// Add current template to printOut queue
		self::$LoadedTemplate[] = $this;
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

	// Parse all queue into parse pool or export to specified variable
	public function parse(&$export = null) {
		if (!$this->parsed) {
			$this->parsedContent = $this->rootBlock->parse();
			self::$TemplatePool[] = &$this;
			$this->parsed = true;
		}

		if (isset($export)) {
			$export = $this->parsedContent;
		}
		return $this;
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
			$this->lastQueuePointer = $this->pointer->getQueue();
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
		return (isset($this->assign[$tagName])) ? $this->assign[$tagName] : null;
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

	static public function GetGlobalAssign($tagName) {
		return (isset(self::$GlobalAssign[$tagName])) ? self::$GlobalAssign[$tagName] : null;
	}

	// Print out the parsed content from template pool on screen
	static public function printOut() {
		if (count(self::$TemplatePool)) {
			foreach (self::$TemplatePool as $template) {
				echo $template->getParsedContent();
			}
			self::$TemplatePool = array();
		}
	}

	// Create a customized assign tag processor
	static public function CreateAssignProcessor($name, $callback) {
		if (is_string($name) && is_callable($callback)) {
			self::$AssignCallback[$name] = $callback;
		}
	}

	// Execute customized assign tag processor
	static public function ExecAssignProcessor($name, &$assignTag) {
		if (is_string($name) && is_string($assignTag)) {
			if (isset(self::$AssignCallback[$name])) {
				return call_user_func_array(self::$AssignCallback[$name]￼, array($assignTag));
			}
		}
		return $assignTag;
	}
}

class TemplateBlock {
	private $blockName = '';
	private $blockType = 'BLOCK';
	private $blockContent = Array();
	private $templateContainer = null;
	private $lastIndex = 0;
	private $parentBlock;
	private $isRoot = false;

	public function __construct($templateContainer, $blockName, $tplContent, $offset = 0, $blockType = 'BLOCK', $parentBlock = null) {
		// Setup the block name, type and parent
		$this->blockName = $blockName;
		$this->blockType = $blockType;
		$this->parentBlock = $parentBlock;
		$this->templateContainer = $templateContainer;

		for ($index = $offset, $length = count($tplContent); $index < $length; $index++) {
			// Check if it is a block tag
			if (preg_match('/<!\-\- (START|END) ([a-z\_]+): (.+) \-\->/i', $tplContent[$index], $matches)) {
				if ($matches[1] == 'START') {
					// Create a template block under current block
					$tplObject = new TemplateBlock($this->templateContainer, $matches[3], $tplContent, $index + 1, $matches[2], $this);

					$this->blockContent[$matches[3]] = $tplObject;
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
		if (is_null($parentBlock)) {
			$this->isRoot = true;
		}
	}

	// Check the block is exists or not under current block
	public function hasBlock($blockName) {
		return (isset($this->blockContent[$blockName]));
	}

	// Get the block
	public function getBlock($blockName) {
		if (isset($this->blockContent[$blockName])) {
			return $this->blockContent[$blockName];
		}
		return null;
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

	// Set the block pointer
	public function setBlock($blockName) {
		if (!isset($this->queue[$blockName])) {
			$this->queue[$blockName] = array();
		}
		$this->blockPointer = $blockName;
		return $this;
	}

	// Get Current Queue
	public function getCurrentQueue() {
		return $this->pointer;
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
			return end($this->queue[$this->blockPointer]);
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

	public function parse() {
		// Get the parent template block content
		$templateContent = $this->parent->getBlockContent();

		// Initialize
		$readyParseBlock = array();
		$parsedContent = '';

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

			// Search and Replace the assigne tag
			$parsedContent = preg_replace_callback(
				'/({(([\w_]+)::)?([\w_]+)})/u',
				function($matches) {
					if ($matches[3]) {
						$assignTag = $matches[4];
						return Template::ExecAssignProcessor($matches[3], $assignTag);
					} else {
						if (isset($this->assign[$matches[4]])) {
							// Queue level assign
							return $this->assign[$matches[4]];
						} elseif (($result = $this->parent->getContainer()->getAssign($matches[4])) !== null) {
							// Template Container level assign
							return $result;
						} elseif (($result = Template::GetGlobalAssign($matches[4])) !== null) {
							// Global level assign
							return $result;
						} else {
							return $matches[0];
						}
					}
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
