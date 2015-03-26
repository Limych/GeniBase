<?php

/**
 * @group transcriptor
 */
class Tests_Transcriptor_PL extends GB_UnitTestCase {
	function test_transcribes() {
		$data = array(
				'ru' => array(
					'Adam'			=> 'Адам',				'Adrian'		=> 'Адриан',
					'Andrzej'		=> 'Анджей',			'Antoni'		=> 'Антоний',
					'Badkowo'		=> 'Бондково',			'Balcerzak'		=> 'Бальцежак',
					'Bogdan'		=> 'Богдан',			'Brodzka'		=> 'Бродская',
					'Brodzki'		=> 'Бродский',			'Celina'		=> 'Целина',
					'Celjowski'		=> 'Цельовский',		'Chajecki'		=> 'Хаенцкий',
					'Chamiec'		=> 'Хамец',				'Ciaglo'		=> 'Ционгло',
					'Ciepka'		=> 'Цемпка',			'Cieslak'		=> 'Цесляк',
					'Ciolek'		=> 'Циолек',			'Cirlic'		=> 'Цирлиць',
					'Czerniatowicz'	=> 'Чернятович',		'Dabkowo'		=> 'Домбково',
					'Derdowski'		=> 'Дердовский',		'Dziamba'		=> 'Дзёмба',
					'Dzianisz'		=> 'Дзяниш',			'Dzunkowski'	=> 'Джунковский',
					'Emil'			=> 'Эмиль',				'Eugeniusz'		=> 'Эугениуш',
					'Filip'			=> 'Филип',				'Gjadla'		=> 'Гьондла',
					'Gotard'		=> 'Готард',			'Huta'			=> 'Хута',
					'Jacek'			=> 'Яцек',				'Jaczkowski'	=> 'Йончковский',
					'Jakobik'		=> 'Якубик',			'Jebrzycki'		=> 'Ембжицкий',
					'Joniec'		=> 'Йонец',				'Juliusz'		=> 'Юлиуш',
					'Kopjewski'		=> 'Копьевский',		'Koscinska'		=> 'Косьциньская',
					'Krystyna'		=> 'Кристина',			'Krzysztof'		=> 'Кшиштоф',
					'Ksawery'		=> 'Ксаверий',			'Kyrljecik'		=> 'Кырльенцик',
					'Lacaz'			=> 'Ляцаз',				'Lodynski'		=> 'Лодыньский',
					'Lorentowicz'	=> 'Лёрентович',		'Lubecki'		=> 'Любецкий',
					'Maciejowice'	=> 'Мацеёвице',			'Marian'		=> 'Мариан',
					'Mariusz'		=> 'Мариуш',			'Niedzwiedz'	=> 'Недзведзь',
					'Osjakow'		=> 'Осьякув',			'Piekna'		=> 'Пенкна',
					'Przeglad'		=> 'Пшеглёнд',			'Przyjaciolka'	=> 'Пшиячулка',
					'Pyru'			=> 'Пыру',				'Roman'			=> 'Роман',
					'Rzeczywistosc'	=> 'Жечивистость',		'Rzytka'		=> 'Житка',
					'Solec'			=> 'Солец',				'Stanislaw'		=> 'Станислав',
					'Stepowska'		=> 'Стемповская',		'Stepowski'		=> 'Стемповский',
					'Szczawiej'		=> 'Щавей',				'Szymon'		=> 'Шимон',
					'Walesa'		=> 'Валенса',			'Wladyslaw'		=> 'Владислав',
					'Wojciech'		=> 'Войцех',			'Zajac'			=> 'Зайонц',
					'Zbigniew'		=> 'Збигнев',			'Ziobro'		=> 'Зёбро',
					'Zolnierz wolnosci'	=> 'Жолнеж вольности',	'Zorz'		=> 'Жорж',
					'Zosia'			=> 'Зося',				'Zrodlowski'	=> 'Зьрудловский',
					'Zwiazkowiec'	=> 'Звёнзковец',		'Zycie'			=> 'Жиче',
					'Сiapala'		=> 'Циомпала',
				)
		);
		foreach ($data as $to_lang => $pairs){
			foreach ($pairs as $src => $res)
				$this->assertEquals($res, GB_Transcriptor::transcript($src, 'pl', $to_lang));
		}
	}

/*	function test_tranliterates() {
		$data = array(
				'ru' => array(
					''	=> '',
				)
		);
		foreach ($data as $to_lang => $pairs){
			foreach ($pairs as $src => $res)
				$this->assertEquals($res, GB_Transcriptor::transcript($src, 'pl', $to_lang, GB_Transcriptor::MODE_TRANSLITERATE));
		}
	}/**/
}
