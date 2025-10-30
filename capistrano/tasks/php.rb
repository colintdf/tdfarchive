
namespace :php do

	desc 'Reload PHP to clear OpCache (necessary!)'
	task :reload do
		on roles(:nginx) do
			if "#{fetch(:stage)}" != 'production' then
				execute "sudo /usr/sbin/service php7.2-fpm reload"
			else 
				execute "sudo /usr/sbin/service php7.4-fpm reload"
			end
		end
	end

end
