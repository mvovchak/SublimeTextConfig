<?php
//Copyright (c) 2014, Carlos C
//All rights reserved.
//
//Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
//
//1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
//
//2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
//
//3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
//
//THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

define("ST_AT", "@");
define("ST_BRACKET_CLOSE", "]");
define("ST_BRACKET_OPEN", "[");
define("ST_COLON", ":");
define("ST_COMMA", ",");
define("ST_CONCAT", ".");
define("ST_CURLY_CLOSE", "}");
define("ST_CURLY_OPEN", "{");
define("ST_DIVIDE", "/");
define("ST_DOLLAR", "$");
define("ST_EQUAL", "=");
define("ST_EXCLAMATION", "!");
define("ST_IS_GREATER", ">");
define("ST_IS_SMALLER", "<");
define("ST_MINUS", "-");
define("ST_MODULUS", "%");
define("ST_PARENTHESES_CLOSE", ")");
define("ST_PARENTHESES_OPEN", "(");
define("ST_PLUS", "+");
define("ST_QUESTION", "?");
define("ST_QUOTE", '"');
define("ST_REFERENCE", "&");
define("ST_SEMI_COLON", ";");
define("ST_TIMES", "*");
define("ST_BITWISE_OR", "|");
define("ST_BITWISE_XOR", "^");
if (!defined("T_POW")) {
	define("T_POW", "**");
}
if (!defined("T_POW_EQUAL")) {
	define("T_POW_EQUAL", "**=");
}
if (!defined("T_YIELD")) {
	define("T_YIELD", "yield");
}
if (!defined("T_FINALLY")) {
	define("T_FINALLY", "finally");
}
;
abstract class Pass {
	protected $indent_size = 1;
	protected $indent_char = "\t";
	protected $block_size = 1;
	protected $new_line = "\n";
	protected $indent = 0;
	protected $for_idx = 0;
	protected $code = '';
	protected $ptr = 0;
	protected $tkns = [];
	protected $use_cache = false;
	protected $cache = [];

	abstract public function vet($source);
	protected function get_token($token) {
		if (!isset($token[1])) {
			return [$token, $token, null];
		} else {
			return $token;
		}
	}
	protected function append_code($code = "", $trim = true) {
		if ($trim) {
			$this->code = rtrim($this->code) . $code;
		} else {
			$this->code .= $code;
		}
	}
	protected function get_crlf_indent($in_for = false, $increment = 0) {
		if ($in_for) {
			++$this->for_idx;
			if ($this->for_idx > 2) {
				$this->for_idx = 0;
			}
		}
		if (0 === $this->for_idx || !$in_for) {
			return $this->get_crlf() . $this->get_indent($increment);
		} else {
			return $this->get_space(false);
		}
	}
	protected function get_crlf($true = true) {
		return $true ? $this->new_line : "";
	}
	protected function get_space($true = true) {
		return $true ? " " : "";
	}
	protected function get_indent($increment = 0) {
		return str_repeat($this->indent_char, ($this->indent + $increment) * $this->indent_size);
	}
	protected function set_indent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}
	protected function inspect_token($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->get_token($this->tkns[$this->ptr + $delta]);
	}
	protected function is_token($token, $prev = false) {
		if ($this->use_cache) {
			$key = ((int) $prev) . "\x2" . (is_array($token) ? implode("\x2", $token) : $token);
			if (isset($this->cache[$key])) {
				return $this->cache[$key];
			}
		}
		$ret = $this->is_token_idx($this->ptr, $token, $prev);
		if ($this->use_cache) {
			$this->cache[$key] = $ret;
		}
		return $ret;
	}
	protected function is_token_idx($idx, $token, $prev = false) {
		$i = $idx;
		if ($prev) {
			while (--$i >= 0 && isset($this->tkns[$i][1]) && T_WHITESPACE === $this->tkns[$i][0]);
		} else {
			$tkns_size = sizeof($this->tkns) - 1;
			while (++$i < $tkns_size && isset($this->tkns[$i][1]) && T_WHITESPACE === $this->tkns[$i][0]);
		}
		if (!isset($this->tkns[$i])) {
			return false;
		}

		$found_token = $this->tkns[$i];
		if ($found_token === $token) {
			return true;
		} elseif (is_array($token) && is_array($found_token) && in_array($found_token[0], $token)) {
			return true;
		} elseif (is_array($token) && is_string($found_token) && in_array($found_token, $token)) {
			return true;
		}

		return false;
	}
	protected function is_token_in_subset($tkns, $idx, $token, $prev = false) {
		$i = $idx;
		if ($prev) {
			while (--$i >= 0 && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		} else {
			$tkns_size = sizeof($tkns) - 1;
			while (++$i < $tkns_size && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		}

		if (!isset($tkns[$i])) {
			return false;
		}

		$found_token = $tkns[$i];
		if ($found_token === $token) {
			return true;
		} elseif (is_array($token) && is_array($found_token)) {
			if (in_array($found_token[0], $token)) {
				return true;
			} elseif ($prev && T_OPEN_TAG === $found_token[0]) {
				return true;
			}
		} elseif (is_array($token) && is_string($found_token) && in_array($found_token, $token)) {
			return true;
		}
		return false;
	}

	protected function prev_token() {
		$i = $this->ptr;
		while (--$i >= 0 && isset($this->tkns[$i][1]) && T_WHITESPACE === $this->tkns[$i][0]);
		return $this->tkns[$i];
	}
	protected function siblings($tkns, $ptr) {
		$i = $ptr;
		while (--$i >= 0 && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		$left = $i;
		$i = $ptr;
		$tkns_size = sizeof($tkns) - 1;
		while (++$i < $tkns_size && isset($tkns[$i][1]) && T_WHITESPACE === $tkns[$i][0]);
		$right = $i;
		return [$left, $right];
	}
	protected function has_ln_after() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspect_token();
		return T_WHITESPACE === $id && $this->has_ln($text);
	}
	protected function has_ln_before() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspect_token(-1);
		return T_WHITESPACE === $id && $this->has_ln($text);
	}
	protected function has_ln_prev_token() {
		list($id, $text) = $this->get_token($this->prev_token());
		return $this->has_ln($text);
	}
	protected function substr_count_trailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}
	protected function print_until_the_end_of_string() {
		$this->print_until_the_end_of(ST_QUOTE);
	}
	protected function walk_until($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
	}
	protected function walk_in_subset_until(&$tkns, $tknid) {
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
	}
	protected function print_until_the_end_of($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text, false);
			if ($tknid == $id) {
				break;
			}
		}
	}
	protected function print_block($start, $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->append_code($text, false);

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}
	protected function walk_and_accumulate_until(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$ret .= $text;
			if ($tknid == $id) {
				return $ret;
			}
		}
	}

	protected function walk_block_and_accumulate(&$tkns, $start, $end) {
		$ret = '';
		$count = 1;
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$ret .= $text;

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}

		return $ret;
	}
	protected function has_ln($text) {
		return (false !== strpos($text, $this->new_line));
	}
}
;
class VetPass extends Pass {
	const OPEN_TAG_RECURSIVE = '<?' . 'php /* \x2 RECURSIVE \x3*/ ';
	public function vet($source, $declared_vars = [], $used_vars = []) {
		$errors = [];

		$tkns = token_get_all($source);
		while (list($index, $token) = each($tkns)) {
			list($id, $text, $line) = $this->get_token($token);
			$ptr = $index;

			if (T_VARIABLE == $id) {
				if ($this->is_token_in_subset($tkns, $ptr, ST_EQUAL) && !isset($declared_vars[trim($text)])) {
					$declared_vars[trim($text)] = [$text, $line];
				} else {
					$used_vars[trim($text)] = [$text, $line];
				}
			}

			if (T_CLASS == $id && !$this->is_token_in_subset($tkns, $ptr, T_DOUBLE_COLON, true)) {
				$this->walk_in_subset_until($tkns, T_FUNCTION);
				prev($tkns);
				continue;
			}

			if (T_LIST == $id) {
				$this->walk_in_subset_until($tkns, ST_PARENTHESES_OPEN);
				$count = 1;
				while (list($index, $token) = each($tkns)) {
					list($id, $text, $line) = $this->get_token($token);
					$ptr = $index;

					if (T_VARIABLE == $id && !isset($declared_vars[trim($text)])) {
						$declared_vars[trim($text)] = [$text, $line];
					}
					if (ST_PARENTHESES_OPEN == $id) {
						++$count;
					}
					if (ST_PARENTHESES_CLOSE == $id) {
						--$count;
					}
					if (0 == $count) {
						break;
					}
				}
			}

			if (T_FOREACH == $id) {
				while (list($index, $token) = each($tkns)) {
					list($id, $text, $line) = $this->get_token($token);
					$ptr = $index;
					if (T_AS == $id) {
						break;
					}
					if (T_VARIABLE == $id) {
						$used_vars[trim($text)] = [$text, $line];
					}
				}

				$count = 1;
				while (list($index, $token) = each($tkns)) {
					list($id, $text, $line) = $this->get_token($token);
					$ptr = $index;

					if (T_VARIABLE == $id && !isset($declared_vars[trim($text)])) {
						$declared_vars[trim($text)] = [$text, $line];
					}
					if (ST_PARENTHESES_OPEN == $id) {
						++$count;
					}
					if (ST_PARENTHESES_CLOSE == $id) {
						--$count;
					}
					if (0 == $count) {
						break;
					}
				}
			}

			if (T_FUNCTION == $id) {
				$this->walk_in_subset_until($tkns, ST_PARENTHESES_OPEN);
				$local_declared_vars = [];
				$base_line = $line;

				$count = 1;
				while (list($index, $token) = each($tkns)) {
					list($id, $text, $line) = $this->get_token($token);
					$ptr = $index;

					if (T_VARIABLE == $id) {
						$local_declared_vars[trim($text)] = [$text, 1];
					}

					if (ST_PARENTHESES_OPEN == $id) {
						++$count;
					}
					if (ST_PARENTHESES_CLOSE == $id) {
						--$count;
					}
					if (0 == $count) {
						break;
					}
				}
				if ($this->is_token_in_subset($tkns, $ptr, ST_SEMI_COLON)) {
					continue;
				}
				if ($this->is_token_in_subset($tkns, $ptr, [T_USE])) {
					$this->walk_in_subset_until($tkns, ST_PARENTHESES_OPEN);
					$count = 1;
					while (list($index, $token) = each($tkns)) {
						list($id, $text, $line) = $this->get_token($token);
						$ptr = $index;

						if (T_VARIABLE == $id) {
							$local_declared_vars[trim($text)] = [$text, 1];
							$used_vars[trim($text)] = [$text, $line];
						}

						if (ST_PARENTHESES_OPEN == $id) {
							++$count;
						}
						if (ST_PARENTHESES_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
					}

				}
				$this->walk_in_subset_until($tkns, ST_CURLY_OPEN);
				$tmp = self::OPEN_TAG_RECURSIVE . ST_CURLY_OPEN . $this->walk_block_and_accumulate($tkns, ST_CURLY_OPEN, ST_CURLY_CLOSE);

				$tmp_errors = array_map(function ($v) use ($base_line) {
					$v[0] += $base_line - 1;
					return $v;
				}, $this->vet($tmp, $local_declared_vars, []));

				if (sizeof($tmp_errors) > 0) {
					array_push($errors, ...$tmp_errors);
				}
			}
		}

		unset($used_vars['$this']);

		$declared_unused = array_diff_key($declared_vars, $used_vars);
		$undeclared_used = array_diff_key($used_vars, $declared_vars);

		foreach ($declared_unused as $v) {
			$errors[] = [
				$v[1],
				'Declared but unused variable: ' . $v[0]
			];
		}
		foreach ($undeclared_used as $v) {
			$errors[] = [
				$v[1],
				'Undeclared variable: ' . $v[0]
			];
		}
		foreach ($used_vars as $k => $v) {
			if (isset($declared_vars[$k]) && $v[1] < $declared_vars[$k][1]) {
				$errors[] = [
					$v[1],
					'Variable used before declaration: ' . $v[0]
				];
			}
		}

		usort($errors, function ($a, $b) {
			return $a[0] > $b[0];
		});

		return $errors;
	}
}
;

if (!isset($testEnv)) {
	$vet = new VetPass();
	if (isset($argv[1]) && is_file($argv[1])) {
		$errors = $vet->vet(file_get_contents($argv[1]));
	} else {
		$errors = $vet->vet(file_get_contents('php://stdin'));
	}
	foreach ($errors as $error) {
		fputcsv(STDOUT, $error, ';');
	}
}