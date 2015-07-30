The example in this folder could be considered a "complete web app." 

ajaxCRUD was not originally intended to be a framework for creating entire web "apps" or functional sites. However, that said, it is a very powerful tool and can be employed to deploy lightweight functional tools very quickly/easliy.

This app was made it in just under 2 hours to help my friends can I easily create and manage attendence of "events" taking place throughout the week/month. 

Requirements:
1) Any person can create an event (events have a date, time, image, and description)
2) Anyone can "opt in" to any active event (i.e. signup) 
3) If a person will be late, they can be able to indicate what time they will show up
4) Ideally only those who sign up can edit/delete their acceptance 


Req 1 accomplished by basic ajaxCRUD interface (events.php)
Req 2 accomplsihed by basic ajaxCRUD interface (index.php) with WHERE clause (addWhereClause) to pull event info
Req 3 accomplished by checkbox (defineCheckbox) and formating the field display (formatFieldWithFunction)
Req 4 accomplished by IP address setting/checking in database and cookie setting and using validateDeleteWithFunction, validateUpdateWithFunction, addValueOnInsert, and onAddExecuteCallBackFunction

This is a fairly basic app, but shows the power of ajaxCRUD in helping make life (and coding) faster and simpler.