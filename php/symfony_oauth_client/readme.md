PHP Symfony Based OAuth2 Client Example w/ Docker
==================================================


This program is a development version of an OAuth2 Client based in PHP using a generic framework (Symfony).
The scope of this program is to fetch resources from Carfax Europe using Carfax's OAuth2 authentication service. 

The code provided in this program is not a production solution and it should be used only as an example or test proof on how an OAuth2 client
could be implemented to connect to Carfax services. The quality and performance of this code is not optimazed.

This code was implemented using the following OAuth2 library: https://github.com/thephpleague/oauth2-client/blob/master/src/Provider/AbstractProvider.php
The use of this library is not mandatory. There are several other libraries which could be used. Please, check https://oauth.net/code/ 
 
 
In order to use this code, please follow below steps:

**Prerequisites:**
 
 * The code was prepared to work easily with docker, so *docker* and *docker-compose* should be installed in your host in order
 to run the client.
 
 * The code should be extracted from the ZIP file or cloned from the repository.
 
 * If you want to use an IDE like PHPStorm and debug the code, you have to include your host IP in *docker-compose.yml* -> XDEBUG_CONFIG: remote_host=x.x.x.x
 >* Then a *PHP Remote Debug* should be set.
 >* A Server should be configure ( hosts 0.0.0.0 and port 8888 by default ).
 >* Then IDE key/session ID should be set with *PHPSTORM* string.
 >* In order to debug with the IDE, a map between the hosts folder code and container folder code should be set.
 >>* /path/to/your/hosts/code/folder -> /app
 >* The vendor libraries gets installed in into the docker container. If you want to debug into the libraries, the content should be copied to the vendor host machine. 

 
 **Steps:**
 
 * Get into the code folder (*symfony_oauth_client* by default )
 
 * Update environment variables CCDID, CLIENT_ID, CLIENT_SECRENT in docker-compose.yml. Check the techdoc provided by Carfax.
 
 * Run the container ( The container will run in *localhost:8888* by default ):
 ```
    docker-compose up
 ```

 * The container should be reachable from our Carfax Gateway, this means that you will need to provide Carfax with the host IP/DNS.
 We recommend a cloud solution like EC2, ECS solutions from AWS, but any other cloud containers provided should be good too.
  
 * The previous step is also needed to include your host in our OAuth whitelist as an allowed redirect_uri.
 ```
    https://your-host-ip-or-dns:8888/authorize
  ```

 * Use a browser to make requests to the containerized client, which will redirect the request to the Carfax Backend (normally was.carfax.eu). 
 In this step the OAuth workflow will be initialized. All the OAuth communications will be managed by the OAuth client automatically.
 
 * Client Endpoints examples:
 
 ```
 
 Example of a direct request to a Carfax Resource: 
    http://localhost:8888/resource/svc/101/vinreg/W0L0TGF08W5159819
    
 Example of an URL resource link generation with checksum token, 
   in this process call 1 is secured by Oauth, and call 2 is secured by the checksum generated in call 1:
   
    Call 1: 
        
       http://localhost:8888/generate_url/vinreg/W0L0TGF08W5159819/svc/101
       
       response:
       
       {
           "url":"http://localhost:8888/resource/svc/101/vinreg/W0L0TGF08W5159819/checksum/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1NTA3NjYyNDh9.ofdHFP8hV1GZZ97u_5Kp3rSyP0BDX2pMWUy30HWIAj8",
           "checksum":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1NTA3NjYyNDh9.ofdHFP8hV1GZZ97u_5Kp3rSyP0BDX2pMWUy30HWIAj8",
           "encoding_token":"252b6b6251c265efca2122f04ae4eef1"
       }
       
     Call 2 using the url provided in response from call 1:
     
       http://localhost:8888/resource/svc/101/vinreg/W0L0TGF08W5159819/checksum/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1NTA3NjYyNDh9.ofdHFP8hV1GZZ97u_5Kp3rSyP0BDX2pMWUy30HWIAj8
 
 ```

  
 