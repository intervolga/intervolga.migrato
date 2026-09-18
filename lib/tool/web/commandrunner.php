<?php namespace Intervolga\Migrato\Tool\Web;

use Bitrix\Main\Localization\Loc;
use Intervolga\Migrato\Tool\Console\Application;
use Intervolga\Migrato\Tool\Console\Formatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

Loc::loadMessages(__FILE__);

/**
 * Запуск консольных команд модуля из административного интерфейса
 */
class CommandRunner
{
	/**
	 * Команды, которые изменяют данные сайта и требуют подтверждения
	 */
	const DANGEROUS_COMMANDS = array(
		'import',
		'importdata',
		'importoptions',
		'importxmlid',
		'import-orm',
		'autofix',
		'cleandeletedxml',
		'unittest',
	);

	/**
	 * Команды, для которых веб-интерфейс не имеет смысла
	 */
	const HIDDEN_COMMANDS = array(
		'help',
		'list',
		'completion',
		'_complete',
		'checkexec',
	);

	/**
	 * Команды, вынесенные в быстрый запуск
	 */
	const MAIN_COMMANDS = array(
		'export',
		'import',
		'validate',
		'snapshot',
		'log',
	);

	/**
	 * @var \Intervolga\Migrato\Tool\Console\Application
	 */
	protected static $application = null;

	/**
	 * @return \Intervolga\Migrato\Tool\Console\Application
	 */
	public static function getApplication()
	{
		if (!static::$application)
		{
			static::$application = new Application();
			static::$application->setAutoExit(false);
			static::$application->setCatchExceptions(true);
		}

		return static::$application;
	}

	/**
	 * Список команд, доступных в веб-интерфейсе
	 *
	 * @return \Symfony\Component\Console\Command\Command[]
	 */
	public static function getCommands()
	{
		$result = array();
		foreach (static::getApplication()->all() as $name => $command)
		{
			if (in_array($name, static::HIDDEN_COMMANDS, true))
			{
				continue;
			}
			if ($command->isHidden())
			{
				continue;
			}
			$result[$name] = $command;
		}
		ksort($result);

		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return \Symfony\Component\Console\Command\Command|null
	 */
	public static function getCommand($name)
	{
		$commands = static::getCommands();

		return isset($commands[$name]) ? $commands[$name] : null;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public static function isDangerous($name)
	{
		return in_array($name, static::DANGEROUS_COMMANDS, true);
	}

	/**
	 * Собирает параметры запуска команды из данных запроса.
	 * Возвращает массив в формате ArrayInput
	 *
	 * @param \Symfony\Component\Console\Command\Command $command
	 * @param array $request
	 *
	 * @return array
	 */
	public static function extractParameters(Command $command, array $request)
	{
		$result = array();
		$definition = $command->getDefinition();
		foreach ($definition->getArguments() as $argument)
		{
			$key = 'ARG_' . $argument->getName();
			$value = isset($request[$key]) ? trim((string)$request[$key]) : '';
			if ($value !== '')
			{
				$result[$argument->getName()] = $value;
			}
		}
		foreach ($definition->getOptions() as $option)
		{
			$key = 'OPT_' . $option->getName();
			if (!isset($request[$key]))
			{
				continue;
			}
			if ($option->acceptValue())
			{
				$value = trim((string)$request[$key]);
				if ($value !== '')
				{
					$result['--' . $option->getName()] = $value;
				}
			}
			elseif ($request[$key] === 'Y')
			{
				$result['--' . $option->getName()] = true;
			}
		}
		if (isset($request['OPT_fails']) && $request['OPT_fails'] === 'Y'
			&& !$definition->hasOption('fails'))
		{
			$result['--fails'] = true;
		}

		return $result;
	}

	/**
	 * Проверяет, что переданы все обязательные аргументы команды
	 *
	 * @param \Symfony\Component\Console\Command\Command $command
	 * @param array $parameters
	 *
	 * @return string[] список ошибок
	 */
	public static function validateParameters(Command $command, array $parameters)
	{
		$errors = array();
		foreach ($command->getDefinition()->getArguments() as $argument)
		{
			if ($argument->isRequired() && !isset($parameters[$argument->getName()]))
			{
				$errors[] = Loc::getMessage(
					'INTERVOLGA_MIGRATO.WEB_ARGUMENT_REQUIRED',
					array('#ARGUMENT#' => $argument->getName())
				);
			}
		}

		return $errors;
	}

	/**
	 * Уровни подробности вывода
	 *
	 * @return string[]
	 */
	public static function getVerbosityLevels()
	{
		return array(
			(string)OutputInterface::VERBOSITY_NORMAL => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_VERBOSITY_NORMAL'),
			(string)OutputInterface::VERBOSITY_VERBOSE => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_VERBOSITY_VERBOSE'),
			(string)OutputInterface::VERBOSITY_VERY_VERBOSE => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_VERBOSITY_VERY_VERBOSE'),
			(string)OutputInterface::VERBOSITY_DEBUG => Loc::getMessage('INTERVOLGA_MIGRATO.WEB_VERBOSITY_DEBUG'),
		);
	}

	/**
	 * @param int $verbosity
	 *
	 * @return int
	 */
	public static function normalizeVerbosity($verbosity)
	{
		$levels = static::getVerbosityLevels();
		$verbosity = (int)$verbosity;
		if (!array_key_exists((string)$verbosity, $levels))
		{
			$verbosity = OutputInterface::VERBOSITY_NORMAL;
		}

		return $verbosity;
	}

	/**
	 * Запускает команду, выводя лог выполнения в браузер
	 *
	 * @param string $name имя команды
	 * @param array $parameters параметры в формате ArrayInput
	 * @param int $verbosity уровень подробности вывода
	 *
	 * @return int код возврата команды
	 * @throws \Exception
	 */
	public static function run($name, array $parameters = array(), $verbosity = OutputInterface::VERBOSITY_NORMAL)
	{
		@set_time_limit(0);
		ignore_user_abort(true);

		$parameters = array_merge(array('command' => $name), $parameters);
		$input = new ArrayInput($parameters);
		$input->setInteractive(false);

		$output = new HtmlOutput(static::normalizeVerbosity($verbosity), new Formatter(true));

		return static::getApplication()->run($input, $output);
	}

	/**
	 * Строка запуска команды в консоли, чтобы её можно было скопировать
	 *
	 * @param string $name
	 * @param array $parameters
	 * @param int $verbosity
	 *
	 * @return string
	 */
	public static function getConsoleString($name, array $parameters = array(), $verbosity = OutputInterface::VERBOSITY_NORMAL)
	{
		$result = 'php run.php ' . $name;
		foreach ($parameters as $key => $value)
		{
			if ($value === true)
			{
				$result .= ' ' . $key;
			}
			elseif (strpos((string)$key, '--') === 0)
			{
				$result .= ' ' . $key . '=' . escapeshellarg($value);
			}
			else
			{
				$result .= ' ' . escapeshellarg($value);
			}
		}
		$verbosityFlags = array(
			OutputInterface::VERBOSITY_VERBOSE => ' -v',
			OutputInterface::VERBOSITY_VERY_VERBOSE => ' -vv',
			OutputInterface::VERBOSITY_DEBUG => ' -vvv',
		);
		if (isset($verbosityFlags[$verbosity]))
		{
			$result .= $verbosityFlags[$verbosity];
		}

		return $result;
	}
}
