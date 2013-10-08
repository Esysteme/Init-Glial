;[name_of_connection] => will be acceded in framework with $this->db['name_of_connection']->method()
;driver => list of SGBD avaible {mysql, postgresql, sybase, oracle}
;hostname => server_name of ip of server SGBD (better to put localhost or real IP)
;user => user who will be used to connect to the SGBD
;password => password who will be used to connect to the SGBD
;database => database / schema witch will be used to access to datas

[default]
driver=mysql
hostname=localhost
user=root
password=
database=test