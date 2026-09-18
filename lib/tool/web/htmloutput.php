<?php namespace Intervolga\Migrato\Tool\Web;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\Output;

/**
 * Вывод команды symfony/console в браузер: ANSI-разметка преобразуется в HTML
 * и сразу отдаётся клиенту, чтобы лог выполнения появлялся по мере работы команды.
 */
class HtmlOutput extends Output
{
	/**
	 * @var \Intervolga\Migrato\Tool\Web\HtmlConverter
	 */
	protected $converter;

	public function __construct(
		$verbosity = self::VERBOSITY_NORMAL,
		?OutputFormatterInterface $formatter = null
	)
	{
		parent::__construct($verbosity, true, $formatter);
		$this->converter = new HtmlConverter();
	}

	protected function doWrite(string $message, bool $newline): void
	{
		echo $this->converter->convert($message);
		if ($newline)
		{
			echo PHP_EOL;
		}
		$this->flushBuffers();
	}

	protected function flushBuffers()
	{
		while (ob_get_level() > 0)
		{
			ob_end_flush();
		}
		flush();
	}
}
