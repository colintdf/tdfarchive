
namespace :nginx do

	desc 'Remove NGINX Cache files to force regeneration.'
	task :flush do
		on roles(:nginx) do
			execute "sudo /usr/bin/find /var/run/nginx-cache -type f -delete"
		end
	end

	desc 'Reload NGINX valid configs.'
	task :reload do
		on roles(:nginx) do
			execute "sudo /usr/sbin/nginx -t && sudo /usr/sbin/service nginx reload"
		end
	end

end
