#!/usr/bin/env php
<?php
// DEFINES APPLICATION PATH
defined('BEABA_PATH') OR define(
        'BEABA_PATH', !empty($_SERVER['BEABA_PATH']) ?
            $_SERVER['BEABA_PATH'] :
            '..'
);
require_once BEABA_PATH . '/bootstrap.php';
// CONFIGURE THE SCRIPT
$app = new beaba\core\Batch(
    array(
        'infos' => array(
            'name' => 'beabaBuilder',
            'title' => 'Beaba Builder Script',
            'description' => 'Use this script to build beaba applications '
            . 'and improve theirs' . "\n"
            . 'performances with an OPCACHE engine',
            'author' => 'I.CHIRIAC'
        ),
        'options' => array(
            'target' => array(
                'title' => 'The building target file',
                'type' => 'target',
                'alias' => 't',
                'required' => true
            ),
            'basedir' => array(
                'title' => 'The building directory',
                'type' => 'directory',
                'alias' => 'd',
                'required' => true
            ),
            'files' => array(
                'title' => 'List of files to build',
                'type' => 'files',
                'alias' => 'f',
                'required' => true
            ),
            'config' => array(
                'title' => 'List of configuration to build',
                'type' => 'files',
                'alias' => 'c',
                'required' => true
            ),
            'prefix' => array(
                'title' => 'The configuration prefix : core, app, local',
                'type' => 'string',
                'alias' => 'p',
                'required' => true
            ),
            'format' => array(
                'title' => 'Format the php code',
                'type' => 'flag',
                'default' => true
            ),
            'comments' => array(
                'title' => 'Removes comments',
                'type' => 'flag',
                'default' => 'true'
            )
        )
    )
);
// RUN THE SCRIPT
$app->dispatch(function( beaba\core\Batch $app, $args ) {
        $out = $app->getResponse();
        $f = fopen($args['target'] . '.tmp', 'w+');
        fwrite($f, '<?php // BUILD ' . date('Y-m-d H:i:s') . "\n");
        $out->writeLine('Building classes');
        $tloc = 0;
        foreach ($args['files'] as $target) {
            $loc = buildFile(
                $f, $target, $args['comments'], $args['format']
            );
            $out->writeLine(' - ' . $target . ' : ' . $loc);
            $tloc += $loc;
        }
        $out->writeLine('Lines of code : ' . $tloc . "\n");

        $tloc = 0;
        $out->writeLine('Building the configuration');
        foreach ($args['config'] as $target) {
            $file = $args['basedir'] . '/config/' . $target . '.php';
            $loc = buildConfig(
                $f, $file, $target, $args['prefix'], $args['comments'], $args['format']
            );
            $tloc += $loc;
            $out->writeLine(' - ' . $file . ' : ' . $loc);
        }
        $out->writeLine('Lines of code : ' . $tloc . "\n");
        fclose($f);
        // check the file syntax
        ob_start();
        system('php -l ' . $args['target'] . '.tmp', $ret);
        $output = ob_get_clean();
        if ($ret !== 0) {
            $app->getLogger()->error($output);
            exit(1);
        } else {
            $out->writeLine("\n" . $output);
            ob_start();
            system('php -f ' . $args['target'] . '.tmp', $ret);
            $output = ob_get_clean();
            if ($ret !== 0) {
                $app->getLogger()->error($output);
            } else {
                $out->writeLine("Runtime check passed\n" . $output);
                // save the file
                if (file_exists($args['target'])) {
                    rename($args['target'], $args['target'] . '.old');
                }
                rename($args['target'] . '.tmp', $args['target']);
            }
        }
        exit(0);
    });

/**
 * Build the specified configuration file
 * @param type $f
 * @param type $file 
 */
function buildConfig($f, $file, $name, $prefix, $comments, $format)
{
    $tokens = token_get_all(file_get_contents($file));
    if ($tokens[0][0] !== T_OPEN_TAG) {
        throw new Exception(
            'Bad ' . $file . ' format, expecting an OPEN_TAG'
        );
    }
    $tsize = count($tokens);
    for ($offset = 1; $offset < $tsize; $offset++) {
        if (is_array($tokens[$offset]) && $tokens[$offset][0] === T_RETURN) {
            break;
        }
    }

    if ($tokens[$offset][0] !== T_RETURN) {
        throw new Exception(
            'Bad ' . $file . ' format, expecting an RETURN'
        );
    }

    fwrite($f, '// ' . $file . "\n");
    fwrite($f, 'function config_' . $prefix . '_' . strtr($name, '/.', '__'));
    fwrite($f, '() { ' . "\n");
    $loc = writeCode($f, $tokens, $comments, $format, $offset);
    fwrite($f, '}' . "\n");
    return $loc;
}

/**
 * Build the specified php script
 * @param type $f
 * @param type $file
 * @param type $comments
 * @param type $format 
 */
function buildFile($f, $file, $comments, $format)
{
    $tokens = token_get_all(file_get_contents($file));
    if ($tokens[0][0] !== T_OPEN_TAG) {
        throw new Exception(
            'Bad ' . $file . ' format, expecting an OPEN_TAG'
        );
    }
    array_shift($tokens);
    fwrite($f, '// ' . $file . "\n");
    return writeCode($f, $tokens, $comments, $format);
}

/**
 * Filters tokens and write code to the specified file handle
 * @param ressource $target
 * @param array $tokens
 * @param boolean $comments
 * @param boolean $format
 * @param int $offset
 * @return int 
 */
function writeCode($target, $tokens, $comments, $format, $offset = 0)
{
    $allow = false;
    $level = 0;
    $tsize = count($tokens);
    $loc = 0;
    for ($i = $offset; $i < $tsize; $i++) {
        $tok = $tokens[$i];
        if (is_array($tok)) {
            if ($format && $tok[0] === T_WHITESPACE) {
                if (strpos($tok[1], "\n") !== false) {
                    if (!$allow) {
                        $tok[1] = '';
                    } else {
                        $allow = false;
                        if (
                            $i + 1 < $tsize && (
                            $tokens[$i + 1] == '}'
                            )
                        )
                            $level--;
                        $tok[1] = "\n" . str_repeat(' ', $level * 4);
                        $loc++;
                    }
                }
            }
            if (
                !$comments || (
                $tok[0] !== T_DOC_COMMENT &&
                $tok[0] !== T_COMMENT
                )
            ) {
                fwrite($target, $tok[1]);
            }
        } else {
            if ($format) {
                if (
                    $tok === '{' ||
                    $tok === ';' ||
                    $tok === ',' ||
                    $tok === '}'
                ) {
                    if ($tok === '{')
                        $level++;
                    $allow = true;
                }
            }
            fwrite($target, $tok);
        }
    }
    return $loc;
}