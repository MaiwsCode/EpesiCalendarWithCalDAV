# EpesiCalendarWithCalDAV

W Panelu administratora w przeglądarce rekordów dla tabeli 'contact' dodałem tabele 'calendar url'    
W programie zapytania zwiazane z pobraniem listy użytkownikow posiadajacych linki odbywa się w tabeli 'contact_data_1'  
W radicale dla każdego użytkownika utworzyć osobny kalendarz a konto logowania radicale wspolne  
Linki z Radicale przypiąć w EPESI dla każdego użytkownika  
Login i haslo wpisać w pliku 'iCalSyncCommon_0' dla zmiennych $login i $password  linia 23 i 70  
Do każdej z tabeli (task,phonecall,crm_meeting) dodać trzeba pole uid(text)

Testowane na localhoscie.
